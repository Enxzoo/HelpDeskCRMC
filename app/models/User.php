<?php
/**
 * User.php
 * Data-access for the "users" table.
 */

require_once __DIR__ . '/../config/database.php';

class User
{
    private mysqli $db;

    public function __construct()
    {
        $this->db = getDbConnection();
    }

    public function findById(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE user_id = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_assoc() : null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_assoc() : null;
    }

    public function findByRole(string $role): array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE role = ? AND is_active = 1');
        $stmt->bind_param('s', $role);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function findByOffice(int $officeId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE office_id = ? AND role IN ("staff", "admin") AND is_active = 1');
        $stmt->bind_param('i', $officeId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (role, office_id, student_number, first_name, last_name, email, password_hash, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
        );

        $stmt->bind_param(
            'sisisss',
            $data['role'],
            $data['office_id'] ?? null,
            $data['student_number'] ?? null,
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['password_hash']
        );

        $stmt->execute();
        return $stmt->insert_id;
    }

    public function updateLastLogin(int $userId): void
    {
        $stmt = $this->db->prepare('UPDATE users SET last_login_at = NOW() WHERE user_id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
    }
}
