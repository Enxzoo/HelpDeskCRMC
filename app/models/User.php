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

    public function findByStudentNumber(string $studentNumber): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE student_number = ? LIMIT 1');
        $stmt->bind_param('s', $studentNumber);
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
        $role = $data['role'];
        $officeId = $data['office_id'] ?? null;
        $studentNumber = $data['student_number'] ?? null;
        $firstName = $data['first_name'];
        $lastName = $data['last_name'];
        $email = $data['email'];
        $passwordHash = $data['password_hash'];
        $stmt = $this->db->prepare(
            'INSERT INTO users (role, office_id, student_number, first_name, last_name, email, password_hash, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
        );

        $stmt->bind_param(
            'sisssss',
            $role,
            $officeId,
            $studentNumber,
            $firstName,
            $lastName,
            $email,
            $passwordHash
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

    public function findAll(): array
    {
        return $this->db->query(
            'SELECT u.user_id, u.role, u.office_id, u.student_number,
                    u.first_name, u.last_name, u.email, u.is_active,
                    u.last_login_at, u.created_at, o.office_name
             FROM users u
             LEFT JOIN offices o ON o.office_id = u.office_id
             WHERE u.deleted_at IS NULL
             ORDER BY u.created_at DESC, u.user_id DESC'
        )->fetch_all(MYSQLI_ASSOC);
    }

    public function getStats(): array
    {
        $row = $this->db->query(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(role = "student"), 0) AS students,
                    COALESCE(SUM(role = "staff"), 0) AS staff,
                    COALESCE(SUM(role = "admin"), 0) AS admins,
                    COALESCE(SUM(is_active = 1), 0) AS active,
                    COALESCE(SUM(is_active = 0), 0) AS inactive
             FROM users WHERE deleted_at IS NULL'
        )->fetch_assoc();
        return array_map('intval', $row);
    }

    public function toggleStatus(int $userId): bool
    {
        $this->db->begin_transaction();
        try {
            $stmt = $this->db->prepare('SELECT role, is_active FROM users WHERE user_id = ? AND deleted_at IS NULL FOR UPDATE');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            if (!$user) { $this->db->commit(); return false; }
            if ($user['role'] === 'student') {
                throw new RuntimeException('Student access changes require student account management and a recorded reason.');
            }
            if ($user['role'] === 'staff' && (int)$user['is_active']) {
                $stmt = $this->db->prepare('UPDATE inquiries SET assigned_staff_id = NULL WHERE assigned_staff_id = ? AND status <> "Resolved"');
                $stmt->bind_param('i', $userId);
                $stmt->execute();
            }
            $stmt = $this->db->prepare('UPDATE users SET is_active = 1 - is_active WHERE user_id = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $this->db->commit();
            return true;
        } catch (Throwable $error) {
            $this->db->rollback();
            throw $error;
        }
    }
}
