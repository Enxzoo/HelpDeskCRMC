<?php
require_once __DIR__ . '/../config/database.php';

class ChatSession
{
    private mysqli $db;

    public function __construct()
    {
        $this->db = getDbConnection();
    }

    public static function validKey($key): bool
    {
        return is_string($key) && preg_match('/^[a-f0-9]{32}$/D', $key) === 1;
    }

    public function find(int $studentId, string $key): ?array
    {
        $stmt = $this->db->prepare('SELECT *, UNIX_TIMESTAMP(updated_at) AS updated_epoch FROM chat_sessions WHERE student_id = ? AND session_key = ?');
        $stmt->bind_param('is', $studentId, $key);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function latestForCategory(int $studentId, string $category): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM chat_sessions WHERE student_id = ? AND category = ?
            ORDER BY updated_at DESC, session_id DESC LIMIT 1');
        $stmt->bind_param('is', $studentId, $category);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function recent(int $studentId): array
    {
        $stmt = $this->db->prepare('SELECT session_id, session_key, category, last_message,
            UNIX_TIMESTAMP(updated_at) AS updated_at FROM chat_sessions WHERE student_id = ?
            ORDER BY updated_at DESC, session_id DESC LIMIT 100');
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as &$row) {
            $row['session_id'] = (int) $row['session_id'];
            $row['updated_at'] = (int) $row['updated_at'];
        }
        return $rows;
    }

    public function save(int $studentId, string $category, array $history, ?string $lastMessage, ?string $key = null): array
    {
        // Keep older clients functional without merging explicitly identified new chats.
        if ($key === null)
            $key = $this->latestForCategory($studentId, $category)['session_key'] ?? bin2hex(random_bytes(16));
        if (!self::validKey($key))
            throw new InvalidArgumentException('Invalid conversation ID.');
        $existing = $this->find($studentId, $key);
        if ($existing && $existing['category'] !== $category)
            throw new InvalidArgumentException('Conversation category does not match.');
        $data = json_encode($history, JSON_THROW_ON_ERROR);
        $stmt = $this->db->prepare('INSERT INTO chat_sessions (student_id, session_key, category, session_data, last_message)
            VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE session_data = VALUES(session_data),
            last_message = VALUES(last_message), updated_at = CURRENT_TIMESTAMP');
        $stmt->bind_param('issss', $studentId, $key, $category, $data, $lastMessage);
        $stmt->execute();
        $row = $this->find($studentId, $key);
        return [
            'session_id' => (int) $row['session_id'],
            'session_key' => $key,
            'category' => $row['category'],
            'last_message' => $row['last_message'],
            'updated_at' => (int) $row['updated_epoch']
        ];
    }
}
