<?php
require_once __DIR__ . '/../models/Notification.php';
$autoload = __DIR__ . '/../../vendor/autoload.php';
if (is_file($autoload)) require_once $autoload;

class NotificationDispatcher
{
    private mysqli $db;
    private ?Closure $emailSender;
    private ?Closure $pushSender;

    public function __construct(?Closure $emailSender = null, ?Closure $pushSender = null)
    {
        $this->db = getDbConnection();
        $this->emailSender = $emailSender;
        $this->pushSender = $pushSender;
    }

    public static function emailReady(): bool
    {
        return class_exists(\PHPMailer\PHPMailer\PHPMailer::class)
            && filter_var(env('SMTP_USERNAME', ''), FILTER_VALIDATE_EMAIL) !== false
            && trim((string)env('SMTP_PASSWORD', '')) !== ''
            && trim((string)env('SMTP_HOST', '')) !== '';
    }

    public static function pushReady(): bool
    {
        return class_exists(\Minishlink\WebPush\WebPush::class)
            && trim((string)env('VAPID_PUBLIC_KEY', '')) !== ''
            && trim((string)env('VAPID_PRIVATE_KEY', '')) !== ''
            && trim((string)env('VAPID_SUBJECT', '')) !== '';
    }

    public static function link(string $role, int $inquiryId): string
    {
        $base = rtrim((string)env('APP_URL', 'http://helpdeskcrmc.test'), '/');
        if (!filter_var($base, FILTER_VALIDATE_URL) || !in_array(parse_url($base, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new RuntimeException('A valid APP_URL is required for notification links.');
        }
        $page = $role === 'student' ? 'dashboard_student.php' : ($role === 'admin' ? 'dashboard_admin.php' : 'dashboard_staff.php');
        return $base . '/' . $page . '?inquiry_id=' . $inquiryId;
    }

    public function runBatch(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $stats = ['sent' => 0, 'skipped' => 0, 'failed' => 0, 'deferred' => 0];
        $emailReady = (int)($this->emailSender !== null || self::emailReady());
        $pushReady = (int)($this->pushSender !== null || self::pushReady());
        $rows = $this->db->query('SELECT d.*, n.user_id, n.inquiry_id, n.title, n.message,
            u.email, u.role, COALESCE(p.email_enabled, 1) AS email_enabled,
            (u.is_active = 1 AND (n.inquiry_id IS NULL OR u.role = "admin"
             OR (u.role = "student" AND i.student_id = u.user_id)
             OR (u.role = "staff" AND u.office_id = i.office_id))) AS authorized,
            s.endpoint, s.public_key, s.auth_token, s.content_encoding, s.user_id AS subscription_user_id
            FROM notification_deliveries d JOIN notifications n ON n.notification_id = d.notification_id
            JOIN users u ON u.user_id = n.user_id LEFT JOIN inquiries i ON i.inquiry_id = n.inquiry_id
            LEFT JOIN notification_preferences p ON p.user_id = n.user_id
            LEFT JOIN push_subscriptions s ON s.subscription_id = d.subscription_id
            WHERE ((d.status = "pending" AND d.available_at <= NOW())
               OR (d.status = "processing" AND d.locked_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)))
            AND ((d.channel = "email" AND ' . $emailReady . ' = 1) OR (d.channel = "push" AND ' . $pushReady . ' = 1))
            ORDER BY d.delivery_id LIMIT ' . $limit)->fetch_all(MYSQLI_ASSOC);

        foreach ($rows as $row) {
            $email = $row['channel'] === 'email';
            $skip = !(int)$row['authorized'] || ($email && (!(int)$row['email_enabled'] || !filter_var($row['email'], FILTER_VALIDATE_EMAIL)))
                || (!$email && (!$row['endpoint'] || (int)$row['subscription_user_id'] !== (int)$row['user_id']));
            if (!$skip && (($email && $this->emailSender === null && !self::emailReady())
                || (!$email && $this->pushSender === null && !self::pushReady()))) {
                $stats['deferred']++;
                continue;
            }
            $id = (int)$row['delivery_id'];
            $token = bin2hex(random_bytes(16));
            $claim = $this->db->prepare('UPDATE notification_deliveries SET status = "processing", worker_token = ?, locked_at = NOW()
                WHERE delivery_id = ? AND ((status = "pending" AND available_at <= NOW())
                OR (status = "processing" AND locked_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)))');
            $claim->bind_param('si', $token, $id);
            $claim->execute();
            if (!$claim->affected_rows) continue;
            $status = 'skipped';
            $error = null;
            $attempts = (int)$row['attempts'];
            $delay = 0;
            try {
                if (!$skip) {
                    $attempts++;
                    $row['url'] = self::link($row['role'], (int)$row['inquiry_id']);
                    $result = $email ? ($this->emailSender !== null ? ($this->emailSender)($row) : $this->sendEmail($row))
                        : ($this->pushSender !== null ? ($this->pushSender)($row) : $this->sendPush($row));
                    if ($result === 'expired' && !$email) {
                        (new Notification())->unsubscribe((int)$row['user_id'], $row['endpoint']);
                        $stats['skipped']++;
                        continue;
                    }
                    if ($result !== true) throw new RuntimeException('Delivery was not accepted.');
                    $status = 'sent';
                }
            } catch (Throwable $exception) {
                $status = $attempts >= 5 ? 'failed' : 'pending';
                $error = $email ? 'Email delivery failed. Check SMTP configuration or retry later.' : 'Push delivery failed. Check VAPID configuration or retry later.';
                $delay = min(3600, 30 * (2 ** min($attempts, 7)));
                error_log('Notification delivery ' . $id . ' failed (' . $row['channel'] . ').');
            }
            $update = $this->db->prepare('UPDATE notification_deliveries SET status = ?, attempts = ?, last_error = ?,
                sent_at = IF(? = "sent", NOW(), NULL), available_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
                worker_token = NULL, locked_at = NULL WHERE delivery_id = ? AND worker_token = ?');
            $update->bind_param('sissiis', $status, $attempts, $error, $status, $delay, $id, $token);
            $update->execute();
            $stats[$status === 'pending' ? 'failed' : $status]++;
        }
        return $stats;
    }

    private function sendEmail(array $row): bool
    {
        return $this->buildEmail($row)->send();
    }

    private function buildEmail(array $row): \PHPMailer\PHPMailer\PHPMailer
    {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string)env('SMTP_HOST', 'smtp.gmail.com');
        $mail->SMTPAuth = true;
        $mail->Username = (string)env('SMTP_USERNAME', '');
        $mail->Password = (string)env('SMTP_PASSWORD', '');
        $encryption = (string)env('SMTP_ENCRYPTION', 'tls');
        if (!in_array($encryption, ['tls', 'ssl'], true)) throw new RuntimeException('SMTP requires TLS.');
        $mail->SMTPSecure = $encryption === 'ssl' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)env('SMTP_PORT', 587);
        $mail->Timeout = 10;
        $mail->getSMTPInstance()->Timelimit = 20;
        $mail->CharSet = 'UTF-8';
        $from = trim((string)env('SMTP_FROM_EMAIL', '')) ?: $mail->Username;
        $mail->setFrom($from, (string)env('SMTP_FROM_NAME', 'Helpdesk CRMC'));
        $mail->addAddress($row['email']);
        $mail->MessageID = '<notification-' . $row['notification_id'] . '-' . $row['user_id'] . '@' . substr(strrchr($from, '@'), 1) . '>';
        $mail->Subject = 'Helpdesk CRMC: ' . $row['title'];
        $mail->isHTML(true);
        $escape = static fn($text) => htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
        $mail->Body = '<h2>' . $escape($row['title']) . '</h2><p>' . $escape($row['message'])
            . '</p><p><a href="' . $escape($row['url']) . '">Open concern</a></p><p>Helpdesk CRMC</p>';
        $mail->AltBody = $row['title'] . "\n\n" . $row['message'] . "\n\nOpen concern: " . $row['url'];
        return $mail;
    }

    private function sendPush(array $row): bool|string
    {
        $subscription = \Minishlink\WebPush\Subscription::create([
            'endpoint' => $row['endpoint'], 'publicKey' => $row['public_key'],
            'authToken' => $row['auth_token'], 'contentEncoding' => $row['content_encoding'],
        ]);
        $push = new \Minishlink\WebPush\WebPush(['VAPID' => [
            'subject' => env('VAPID_SUBJECT'), 'publicKey' => env('VAPID_PUBLIC_KEY'), 'privateKey' => env('VAPID_PRIVATE_KEY'),
        ]], ['TTL' => 3600, 'urgency' => 'normal'], 10, ['connect_timeout' => 4, 'allow_redirects' => false]);
        $report = $push->sendOneNotification($subscription, json_encode([
            'notification_id' => (int)$row['notification_id'], 'title' => $row['title'],
            'message' => $row['message'], 'url' => ($row['role'] === 'student' ? 'dashboard_student.php' : ($row['role'] === 'admin' ? 'dashboard_admin.php' : 'dashboard_staff.php')) . '?inquiry_id=' . (int)$row['inquiry_id'],
        ], JSON_THROW_ON_ERROR));
        return $report->isSubscriptionExpired() ? 'expired' : $report->isSuccess();
    }
}
