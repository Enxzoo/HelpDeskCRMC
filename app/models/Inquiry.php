<?php
/**
 * Inquiry.php
 * Data-access only: all SQL for the "inquiries" table lives here.
 * No business logic, no HTTP concerns — keeps DB bugs isolated
 * from logic bugs.
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

        $stmt = $this->db->prepare(
            'INSERT INTO inquiries (student_id, office_id, subject, description, status, created_at)
             VALUES (?, ?, ?, ?, "Pending", NOW())'
        );
        $subject = $data['subject'] ?? mb_substr($data['message'], 0, 150);
        $stmt->bind_param(
            'iiss',
            $data['student_id'],
            $officeId,
            $subject,
            $data['message']
        );
        $stmt->execute();

        return $stmt->insert_id;
    }

    public function attachAiResult(int $inquiryId, ?array $match): void
    {
        // AI results are stored in the ai_match_logs table;
        // here we just update the inquiry status.
        $status = $match ? 'Resolved' : 'Pending';

        $stmt = $this->db->prepare(
            'UPDATE inquiries SET status = ? WHERE inquiry_id = ?'
        );
        $stmt->bind_param('si', $status, $inquiryId);
        $stmt->execute();
    }

    public function findByStudent(int $studentId): array
    {
        $stmt = $this->db->prepare(
            'SELECT i.*, o.office_name
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
        $sql = 'SELECT i.*, o.office_name, u.first_name, u.last_name, u.email as student_email, u.student_number,
                       s.first_name as staff_first_name, s.last_name as staff_last_name
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
            $sql .= ' AND i.status = ?';
            $params[] = $status;
            $types .= 's';
        }

        $sql .= ' ORDER BY i.created_at DESC';

        $stmt = $this->db->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $inquiries = $result->fetch_all(MYSQLI_ASSOC);

        foreach ($inquiries as &$inquiry) {
            $inquiry['replies'] = $this->getReplies((int)$inquiry['inquiry_id']);
        }

        return $inquiries;
    }

    public function findById(int $inquiryId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT i.*, o.office_name, u.first_name, u.last_name, u.email as student_email, u.student_number
             FROM inquiries i
             LEFT JOIN offices o ON i.office_id = o.office_id
             LEFT JOIN users u ON i.student_id = u.user_id
             WHERE i.inquiry_id = ?'
        );
        $stmt->bind_param('i', $inquiryId);
        $stmt->execute();
        $result = $stmt->get_result();
        $inquiry = $result->fetch_assoc();

        if ($inquiry) {
            $inquiry['replies'] = $this->getReplies($inquiryId);
        }

        return $inquiry ?: null;
    }

    public function getReplies(int $inquiryId): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, u.first_name, u.last_name, u.role
             FROM inquiry_responses r
             JOIN users u ON r.staff_id = u.user_id
             WHERE r.inquiry_id = ?
             ORDER BY r.created_at ASC'
        );
        $stmt->bind_param('i', $inquiryId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function addReply(int $inquiryId, int $staffId, string $message): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO inquiry_responses (inquiry_id, staff_id, message, created_at)
             VALUES (?, ?, ?, NOW())'
        );
        $stmt->bind_param('iis', $inquiryId, $staffId, $message);
        $stmt->execute();

        // Update inquiry status to In Progress if currently Pending
        $this->db->query("UPDATE inquiries SET status = 'In Progress' WHERE inquiry_id = {$inquiryId} AND status = 'Pending'");

        return $stmt->insert_id;
    }

    public function updateStatus(int $inquiryId, string $status): bool
    {
        $validStatuses = ['Pending', 'In Progress', 'Resolved'];
        if (!in_array($status, $validStatuses, true)) {
            return false;
        }

        $resolvedAt = ($status === 'Resolved') ? 'NOW()' : 'NULL';

        $stmt = $this->db->prepare(
            "UPDATE inquiries SET status = ?, resolved_at = {$resolvedAt} WHERE inquiry_id = ?"
        );
        $stmt->bind_param('si', $status, $inquiryId);
        return $stmt->execute();
    }

    public function getStats(?int $officeId = null): array
    {
        $where = ($officeId !== null && $officeId > 0) ? " WHERE office_id = {$officeId}" : '';

        $totalRes = $this->db->query("SELECT COUNT(*) as cnt FROM inquiries{$where}");
        $total = (int)($totalRes->fetch_assoc()['cnt'] ?? 0);

        $pendingRes = $this->db->query("SELECT COUNT(*) as cnt FROM inquiries WHERE status = 'Pending'" . ($officeId ? " AND office_id = {$officeId}" : ''));
        $pending = (int)($pendingRes->fetch_assoc()['cnt'] ?? 0);

        $inProgressRes = $this->db->query("SELECT COUNT(*) as cnt FROM inquiries WHERE status = 'In Progress'" . ($officeId ? " AND office_id = {$officeId}" : ''));
        $inProgress = (int)($inProgressRes->fetch_assoc()['cnt'] ?? 0);

        $resolvedRes = $this->db->query("SELECT COUNT(*) as cnt FROM inquiries WHERE status = 'Resolved'" . ($officeId ? " AND office_id = {$officeId}" : ''));
        $resolved = (int)($resolvedRes->fetch_assoc()['cnt'] ?? 0);

        return [
            'total'       => $total,
            'pending'     => $pending,
            'in_progress' => $inProgress,
            'resolved'    => $resolved
        ];
    }
}