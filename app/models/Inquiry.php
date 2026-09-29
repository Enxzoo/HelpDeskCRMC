<?php
/**
 * Inquiry.php
 * Complete data-access layer for the "inquiries" table with all necessary methods
 */

require_once __DIR__ . '/../config/database.php';

class Inquiry
{
    private mysqli $db;

    public function __construct()
    {
        $this->db = getDbConnection();
    }

    public function create(array $data): int
    {
        $officeId = $data['office_id'] ?? null;
        $subject = $data['subject'] ?? mb_substr($data['message'], 0, 150);

        $stmt = $this->db->prepare(
            'INSERT INTO inquiries (student_id, office_id, subject, description, status, source, created_at)
             VALUES (?, ?, ?, ?, "Pending", "general_inquiry", NOW())'
        );

        $stmt->bind_param(
            'iiss',
            $data['student_id'],
            $officeId,
            $subject,
            $data['message']
        );

        if (!$stmt->execute()) {
            error_log('Failed to create inquiry: ' . $stmt->error);
            throw new Exception('Failed to create inquiry');
        }

        return $stmt->insert_id;
    }

    public function findByStudent(int $studentId): array
    {
        $stmt = $this->db->prepare(
            'SELECT i.*, o.office_name,
                    CASE
                        WHEN i.status = "Pending" THEN "pending"
                        WHEN i.status = "In Progress" THEN "inprogress"
                        WHEN i.status = "Resolved" THEN "resolved"
                        ELSE "pending"
                    END as status_class
             FROM inquiries i
             LEFT JOIN offices o ON i.office_id = o.office_id
             WHERE i.student_id = ?
             ORDER BY i.created_at DESC'
        );

        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $inquiries = $result->fetch_all(MYSQLI_ASSOC);

        // Attach replies to each inquiry
        foreach ($inquiries as &$inquiry) {
            $inquiry['replies'] = $this->getReplies((int)$inquiry['inquiry_id']);
        }

        return $inquiries;
    }

    public function findByOffice(?int $officeId = null, ?string $status = null): array
    {
        $sql = 'SELECT i.*, o.office_name,
                       CONCAT(u.first_name, " ", u.last_name) as student_name,
                       u.email as student_email,
                       u.student_number,
                       CONCAT(s.first_name, " ", s.last_name) as staff_name
                FROM inquiries i
                LEFT JOIN offices o ON i.office_id = o.office_id
                LEFT JOIN users u ON i.student_id = u.user_id
                LEFT JOIN users s ON i.assigned_staff_id = s.user_id
                WHERE 1=1';

        $params = [];
        $types = '';

        if ($officeId !== null && $officeId > 0) {
            $sql .= ' AND i.office_id = ?';
            $params[] = $officeId;
            $types .= 'i';
        }

        if ($status !== null && $status !== 'all' && $status !== '') {
            $statusMap = [
                'pending' => 'Pending',
                'in_progress' => 'In Progress',
                'resolved' => 'Resolved'
            ];
            $actualStatus = $statusMap[$status] ?? $status;
            $sql .= ' AND i.status = ?';
            $params[] = $actualStatus;
            $types .= 's';
        }

        $sql .= ' ORDER BY i.created_at DESC';

        $stmt = $this->db->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function updateStatus(int $inquiryId, string $status): bool
    {
        $resolvedAt = ($status === 'Resolved') ? 'NOW()' : 'NULL';

        $stmt = $this->db->prepare(
            "UPDATE inquiries
             SET status = ?, resolved_at = $resolvedAt, updated_at = NOW()
             WHERE inquiry_id = ?"
        );

        $stmt->bind_param('si', $status, $inquiryId);
        return $stmt->execute();
    }

    public function assignStaff(int $inquiryId, int $staffId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE inquiries
             SET assigned_staff_id = ?, status = "In Progress", updated_at = NOW()
             WHERE inquiry_id = ?'
        );

        $stmt->bind_param('ii', $staffId, $inquiryId);
        return $stmt->execute();
    }

    public function addResponse(int $inquiryId, int $staffId, string $message): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO inquiry_responses (inquiry_id, staff_id, message, created_at)
             VALUES (?, ?, ?, NOW())'
        );

        $stmt->bind_param('iis', $inquiryId, $staffId, $message);

        if (!$stmt->execute()) {
            error_log('Failed to add response: ' . $stmt->error);
            throw new Exception('Failed to add response');
        }

        return $stmt->insert_id;
    }

    public function getReplies(int $inquiryId): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, CONCAT(u.first_name, " ", u.last_name) as staff_name
             FROM inquiry_responses r
             LEFT JOIN users u ON r.staff_id = u.user_id
             WHERE r.inquiry_id = ?
             ORDER BY r.created_at ASC'
        );

        $stmt->bind_param('i', $inquiryId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getStats(?int $officeId = null): array
    {
        $sql = 'SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = "Pending" THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = "In Progress" THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = "Resolved" THEN 1 ELSE 0 END) as resolved
                FROM inquiries
                WHERE 1=1';

        if ($officeId !== null && $officeId > 0) {
            $sql .= ' AND office_id = ?';
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param('i', $officeId);
        } else {
            $stmt = $this->db->prepare($sql);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function findById(int $inquiryId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT i.*, o.office_name,
                    CONCAT(u.first_name, " ", u.last_name) as student_name,
                    u.email as student_email, u.student_number
             FROM inquiries i
             LEFT JOIN offices o ON i.office_id = o.office_id
             LEFT JOIN users u ON i.student_id = u.user_id
             WHERE i.inquiry_id = ?'
        );

        $stmt->bind_param('i', $inquiryId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function attachAiResult(int $inquiryId, ?array $match): void
    {
        $status = $match ? 'Resolved' : 'Pending';
        $this->updateStatus($inquiryId, $status);
    }
}