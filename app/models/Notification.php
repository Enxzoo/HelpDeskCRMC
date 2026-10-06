<?php
require_once __DIR__ . '/../config/database.php';

class Notification
{
    private mysqli $db;
    private const VISIBLE = 'n.user_id = ? AND u.is_active = 1 AND
        (n.inquiry_id IS NULL OR u.role = "admin"
         OR (u.role = "student" AND i.student_id = u.user_id)
         OR (u.role = "staff" AND u.office_id = i.office_id))';

    public function __construct()
    {
        $this->db = getDbConnection();
    }

    public function record(int $userId, int $inquiryId, string $title, string $message, string $eventKey): ?int
    {
        $existing = $this->db->prepare('SELECT notification_id FROM notification_events WHERE user_id = ? AND event_key = ?');
        $existing->bind_param('is', $userId, $eventKey);
        $existing->execute();
        if ($existing->get_result()->fetch_assoc())
            return null;

        $title = mb_substr($title, 0, 150);
        $message = mb_substr($message, 0, 500);
        $stmt = $this->db->prepare('INSERT INTO notifications (user_id, inquiry_id, title, message) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('iiss', $userId, $inquiryId, $title, $message);
        $stmt->execute();
        $id = $stmt->insert_id;
        $event = $this->db->prepare('INSERT INTO notification_events (user_id, event_key, notification_id) VALUES (?, ?, ?)');
        $event->bind_param('isi', $userId, $eventKey, $id);
        $event->execute();

        if ($this->emailEnabled($userId)) {
            $email = $this->db->prepare('INSERT INTO notification_deliveries (notification_id, channel, target_key) VALUES (?, "email", "email")');
            $email->bind_param('i', $id);
            $email->execute();
        }
        $push = $this->db->prepare('INSERT INTO notification_deliveries (notification_id, channel, target_key, subscription_id)
            SELECT ?, "push", endpoint_hash, subscription_id FROM push_subscriptions WHERE user_id = ?');
        $push->bind_param('ii', $id, $userId);
        $push->execute();
        return $id;
    }

    public function feed(int $userId, ?int $after = null): array
    {
        $joins = ' FROM notifications n JOIN users u ON u.user_id = n.user_id LEFT JOIN inquiries i ON i.inquiry_id = n.inquiry_id ';
        $summary = $this->db->prepare('SELECT COUNT(CASE WHEN n.is_read = 0 THEN 1 END) AS unread_count,
            COALESCE(MAX(n.notification_id), 0) AS latest_id' . $joins . ' WHERE ' . self::VISIBLE);
        $summary->bind_param('i', $userId);
        $summary->execute();
        $stats = $summary->get_result()->fetch_assoc();
        $sql = 'SELECT n.notification_id, n.inquiry_id, n.title, n.message, n.is_read,
            UNIX_TIMESTAMP(n.created_at) AS created_at' . $joins . ' WHERE ' . self::VISIBLE;
        $sql .= $after === null ? ' ORDER BY n.notification_id DESC LIMIT 30' : ' AND n.notification_id > ? ORDER BY n.notification_id ASC LIMIT 100';
        $stmt = $this->db->prepare($sql);
        if ($after === null)
            $stmt->bind_param('i', $userId);
        else
            $stmt->bind_param('ii', $userId, $after);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($items as &$item) {
            $item['notification_id'] = (int) $item['notification_id'];
            $item['inquiry_id'] = $item['inquiry_id'] === null ? null : (int) $item['inquiry_id'];
            $item['is_read'] = (bool) $item['is_read'];
            $item['created_at'] = (int) $item['created_at'];
        }
        return ['items' => $items, 'unread_count' => (int) $stats['unread_count'], 'latest_id' => (int) $stats['latest_id']];
    }

    public function markRead(int $userId, ?int $id = null): bool
    {
        $sql = 'UPDATE notifications n JOIN users u ON u.user_id = n.user_id
            LEFT JOIN inquiries i ON i.inquiry_id = n.inquiry_id SET n.is_read = 1 WHERE ' . self::VISIBLE;
        if ($id !== null)
            $sql .= ' AND n.notification_id = ?';
        $stmt = $this->db->prepare($sql);
        if ($id === null)
            $stmt->bind_param('i', $userId);
        else
            $stmt->bind_param('ii', $userId, $id);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }

    public function emailEnabled(int $userId): bool
    {
        $stmt = $this->db->prepare('SELECT email_enabled FROM notification_preferences WHERE user_id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return (bool) ($stmt->get_result()->fetch_assoc()['email_enabled'] ?? true);
    }

    public function setEmailEnabled(int $userId, bool $enabled): void
    {
        $value = (int) $enabled;
        $stmt = $this->db->prepare('INSERT INTO notification_preferences (user_id, email_enabled) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE email_enabled = VALUES(email_enabled)');
        $stmt->bind_param('ii', $userId, $value);
        $stmt->execute();
    }

    public static function validSubscription(array $subscription): bool
    {
        if (!is_array($subscription['keys'] ?? null))
            return false;
        $endpoint = $subscription['endpoint'] ?? null;
        if (!is_string($endpoint) || strlen($endpoint) > 2048)
            return false;
        $url = parse_url($endpoint);
        if (
            !$url || ($url['scheme'] ?? '') !== 'https' || isset($url['user']) || isset($url['pass'])
            || isset($url['fragment']) || (isset($url['port']) && $url['port'] !== 443)
        )
            return false;
        $host = strtolower($url['host'] ?? '');
        $allowed = false;
        foreach (['fcm.googleapis.com', 'push.services.mozilla.com', 'web.push.apple.com', 'notify.windows.com'] as $domain) {
            if ($host === $domain || ($domain !== 'fcm.googleapis.com' && str_ends_with($host, '.' . $domain)))
                $allowed = true;
        }
        if (!$allowed)
            return false;
        foreach (['p256dh' => 65, 'auth' => 16] as $key => $length) {
            $encoded = $subscription['keys'][$key] ?? null;
            if (!is_string($encoded) || !preg_match('/^[A-Za-z0-9_-]{1,120}={0,2}$/D', $encoded))
                return false;
            $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);
            if ($decoded === false || strlen($decoded) !== $length || ($key === 'p256dh' && $decoded[0] !== "\x04"))
                return false;
        }
        return true;
    }

    public function subscribe(int $userId, array $subscription): void
    {
        if (!self::validSubscription($subscription))
            throw new InvalidArgumentException('Invalid push subscription.');
        $endpoint = $subscription['endpoint'];
        $hash = hash('sha256', $endpoint);
        $publicKey = $subscription['keys']['p256dh'];
        $auth = $subscription['keys']['auth'];
        $stmt = $this->db->prepare('INSERT INTO push_subscriptions (user_id, endpoint, endpoint_hash, public_key, auth_token)
            VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), public_key = VALUES(public_key), auth_token = VALUES(auth_token)');
        $stmt->bind_param('issss', $userId, $endpoint, $hash, $publicKey, $auth);
        $stmt->execute();
    }

    public function unsubscribe(int $userId, string $endpoint): void
    {
        $hash = hash('sha256', $endpoint);
        $stmt = $this->db->prepare('DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint_hash = ?');
        $stmt->bind_param('is', $userId, $hash);
        $stmt->execute();
    }
}
