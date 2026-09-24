<?php
/**
 * User.php
 * Data-access for the "users" table. Auth logic (checking passwords,
 * setting sessions) lives in AuthController, NOT here — this file
 * only talks to the database.
 */

require_once __DIR__ . '/../config/database.php';

class User
{
    private mysqli $db;

    public function __construct()
    {
        $this->db = getDbConnection();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();

        $user = $result->fetch_assoc();
        return $user ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE user_id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();

        $user = $result->fetch_assoc();
        return $user ?: null;
    }

    public function create(string $firstName, string $lastName, string $email, string $password, string $role = 'student', ?int $officeId = null, ?string $studentNumber = null): int
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare(
            'INSERT INTO users (first_name, last_name, email, password_hash, role, office_id, student_number) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('sssssis', $firstName, $lastName, $email, $hash, $role, $officeId, $studentNumber);
        $stmt->execute();

        return $stmt->insert_id;
    }

    public function findAll(): array
    {
        $result = $this->db->query(
            'SELECT u.*, o.office_name
             FROM users u
             LEFT JOIN offices o ON u.office_id = o.office_id
             ORDER BY u.user_id DESC'
        );
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function toggleStatus(int $userId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET is_active = NOT is_active WHERE user_id = ?'
        );
        $stmt->bind_param('i', $userId);
        return $stmt->execute();
    }

    public function getStats(): array
    {
        $students = (int)($this->db->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'student'")->fetch_assoc()['cnt'] ?? 0);
        $staff = (int)($this->db->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'staff'")->fetch_assoc()['cnt'] ?? 0);
        $admins = (int)($this->db->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'admin'")->fetch_assoc()['cnt'] ?? 0);
        $offices = (int)($this->db->query("SELECT COUNT(*) as cnt FROM offices")->fetch_assoc()['cnt'] ?? 0);

        return [
            'students' => $students,
            'staff'    => $staff,
            'admins'   => $admins,
            'offices'  => $offices
        ];
    }
}
