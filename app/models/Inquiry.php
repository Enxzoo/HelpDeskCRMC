<?php
/**
 * Inquiry.php
 * Complete data-access layer for the "inquiries" table with all necessary methods
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/NotificationService.php';
require_once __DIR__ . '/StudentProfile.php';

class DuplicateSubmissionLimitException extends RuntimeException
{
}

class ResponseNotAllowedException extends RuntimeException
{
}

class Inquiry
{
    private mysqli $db;
    public const DUPLICATE_SUBMISSION_LIMIT = 3;
    public const STATUSES = ['Pending', 'In Progress', 'Resolved', 'On Hold'];

    public function __construct()
    {
        $this->db = getDbConnection();
    }

    public function create(
        array $data,
        ?int $duplicateOfInquiryId = null,
        ?float $duplicateConfidence = null
    ): int {
        $officeId = $data['office_id'] ?? null;
        $subject = $data['subject'] ?? mb_substr($data['message'], 0, 150);
        $aiPriority = $data['ai_priority'] ?? null;
        $aiPriorityReason = $data['ai_priority_reason'] ?? null;
        $aiPriorityConfidence = $data['ai_priority_confidence'] ?? null;

        $studentId = (int) $data['student_id'];
        $duplicateRootId = $duplicateOfInquiryId;
        $this->db->begin_transaction();

        try {
            // Serialize submissions by this student so simultaneous duplicates
            // cannot both pass the final three-submission limit check.
            $studentLock = $this->db->prepare(
                'SELECT user_id FROM users WHERE user_id = ? FOR UPDATE'
            );
            $studentLock->bind_param('i', $studentId);
            $studentLock->execute();
            if (!$studentLock->get_result()->fetch_assoc()) {
                throw new RuntimeException('Cannot create an inquiry for an unknown student.');
            }

            if ($duplicateRootId !== null) {
                $rootLock = $this->db->prepare(
                    'SELECT student_id, office_id, status
                     FROM inquiries
                     WHERE inquiry_id = ?
                     FOR UPDATE'
                );
                $rootLock->bind_param('i', $duplicateRootId);
                $rootLock->execute();
                $root = $rootLock->get_result()->fetch_assoc();

                if (
                    !$root
                    || (int) $root['student_id'] !== $studentId
                    || (int) $root['office_id'] !== (int) $officeId
                    || $root['status'] === 'Resolved'
                ) {
                    $duplicateRootId = null;
                    $duplicateConfidence = null;
                } else {
                    $duplicateCount = $this->countRecentDuplicateSubmissions(
                        $studentId,
                        (int) $officeId,
                        $duplicateRootId
                    );
                    if ($duplicateCount >= self::DUPLICATE_SUBMISSION_LIMIT) {
                        throw new DuplicateSubmissionLimitException(
                            'A similar concern has reached the recent submission limit.'
                        );
                    }
                }
            }

            $stmt = $this->db->prepare(
                'INSERT INTO inquiries
                    (student_id, office_id, subject, description, status, source, ai_priority,
                     ai_priority_reason, ai_priority_confidence, duplicate_of_inquiry_id,
                     duplicate_match_confidence, created_at)
                 VALUES (?, ?, ?, ?, "Pending", "general_inquiry", ?, ?, ?, ?, ?, NOW())'
            );

            $stmt->bind_param(
                'iissssdid',
                $studentId,
                $officeId,
                $subject,
                $data['message'],
                $aiPriority,
                $aiPriorityReason,
                $aiPriorityConfidence,
                $duplicateRootId,
                $duplicateConfidence
            );

            if (!$stmt->execute()) {
                error_log('Failed to create inquiry: ' . $stmt->error);
                throw new RuntimeException('Failed to create inquiry.');
            }

            $inquiryId = $stmt->insert_id;
            $snapshot = (new StudentProfile())->snapshot($studentId);
            if ($snapshot !== null) {
                $snapshotJson = json_encode($snapshot, JSON_THROW_ON_ERROR);
                $snapshotStmt = $this->db->prepare('UPDATE inquiries SET student_profile_snapshot = ? WHERE inquiry_id = ?');
                $snapshotStmt->bind_param('si', $snapshotJson, $inquiryId);
                $snapshotStmt->execute();
            }
            (new NotificationService())->created([
                'inquiry_id' => $inquiryId,
                'student_id' => $studentId,
                'office_id' => $officeId,
                'ai_priority' => $aiPriority,
            ]);
            $this->db->commit();
            return $inquiryId;
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    public function findRecentDuplicateCandidates(int $studentId, int $officeId): array
    {
        $stmt = $this->db->prepare(
            '            SELECT inquiry_id, subject, description, status, created_at, duplicate_of_inquiry_id
            FROM inquiries
            WHERE student_id = ?
              AND office_id = ?
              AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY CASE WHEN status = "Resolved" THEN 1 ELSE 0 END, created_at DESC
            LIMIT 8'
        );
        $stmt->bind_param('ii', $studentId, $officeId);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function isActiveDuplicateRoot(int $studentId, int $officeId, int $rootId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT inquiry_id
             FROM inquiries
             WHERE inquiry_id = ?
               AND student_id = ?
               AND office_id = ?
               AND status <> "Resolved"
             LIMIT 1'
        );
        $stmt->bind_param('iii', $rootId, $studentId, $officeId);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc() !== null;
    }

    public function countRecentDuplicateSubmissions(
        int $studentId,
        int $officeId,
        int $duplicateRootId
    ): int {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS submission_count
             FROM inquiries
             WHERE student_id = ?
               AND office_id = ?
               AND status <> "Resolved"
               AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
               AND (inquiry_id = ? OR duplicate_of_inquiry_id = ?)'
        );
        $stmt->bind_param('iiii', $studentId, $officeId, $duplicateRootId, $duplicateRootId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        return (int) ($row['submission_count'] ?? 0);
    }

    public function findByStudent(int $studentId): array
    {
        $stmt = $this->db->prepare(
            'SELECT i.*, o.office_name,
                    CASE
                        WHEN i.status = "Pending" THEN "pending"
                        WHEN i.status = "In Progress" THEN "inprogress"
                        WHEN i.status = "On Hold" THEN "onhold"
                        WHEN i.status = "Resolved" THEN "resolved"
                        ELSE "pending"
                    END as status_class,
                    COALESCE(i.priority_override, i.ai_priority, "Needs triage") as urgency_priority,
                    i.ai_priority,
                    i.ai_priority_reason,
                    i.ai_priority_confidence,
                    i.priority_override,
                    i.priority_override_by,
                    i.priority_override_at
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
            $inquiry['replies'] = $this->getReplies((int) $inquiry['inquiry_id']);
        }

        return $inquiries;
    }

    public function findByOffice(?int $officeId = null, ?string $status = null, bool $prioritizeUrgency = false): array
    {
        $sql = 'SELECT i.*, o.office_name,
                       CONCAT(u.first_name, " ", u.last_name) as student_name,
                       u.email as student_email,
                       u.student_number,
                       CONCAT(s.first_name, " ", s.last_name) as staff_name,
                       CONCAT(p.first_name, " ", p.last_name) as priority_override_staff_name,
                       COALESCE(i.priority_override, i.ai_priority, "Needs triage") as urgency_priority
                FROM inquiries i
                LEFT JOIN offices o ON i.office_id = o.office_id
                LEFT JOIN users u ON i.student_id = u.user_id
                LEFT JOIN users s ON i.assigned_staff_id = s.user_id
                LEFT JOIN users p ON i.priority_override_by = p.user_id
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
                'on_hold' => 'On Hold',
                'resolved' => 'Resolved'
            ];
            $actualStatus = $statusMap[$status] ?? $status;
            $sql .= ' AND i.status = ?';
            $params[] = $actualStatus;
            $types .= 's';
        }

        if ($prioritizeUrgency) {
            $sql .= ' ORDER BY
                        CASE WHEN i.status = "Resolved" THEN 1 ELSE 0 END,
                        CASE
                            WHEN COALESCE(i.priority_override, i.ai_priority) IS NULL THEN 0
                            WHEN COALESCE(i.priority_override, i.ai_priority) = "Critical/Urgent" THEN 1
                            WHEN COALESCE(i.priority_override, i.ai_priority) = "High" THEN 2
                            WHEN COALESCE(i.priority_override, i.ai_priority) = "Normal" THEN 3
                            WHEN COALESCE(i.priority_override, i.ai_priority) = "Low" THEN 4
                            ELSE 0
                        END,
                        i.created_at ASC';
        } else {
            $sql .= ' ORDER BY i.created_at DESC';
        }

        $stmt = $this->db->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        return array_map([StudentProfile::class, 'concernSummary'], $result->fetch_all(MYSQLI_ASSOC));
    }

    public function updateStatus(int $inquiryId, string $status): bool
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Invalid inquiry status.');
        }
        $this->db->begin_transaction();
        try {
            $inquiry = $this->lockInquiry($inquiryId);
            if ($inquiry['status'] !== $status) {
                $this->writeStatus($inquiryId, $status);
                $inquiry['status'] = $status;
                (new NotificationService())->status($inquiry);
            }
            $this->db->commit();
            return true;
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    private function lockInquiry(int $inquiryId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM inquiries WHERE inquiry_id = ? FOR UPDATE');
        $stmt->bind_param('i', $inquiryId);
        $stmt->execute();
        $inquiry = $stmt->get_result()->fetch_assoc();
        if (!$inquiry)
            throw new RuntimeException('Concern not found.');
        return $inquiry;
    }

    private function writeStatus(int $inquiryId, string $status): void
    {
        $resolvedAt = ($status === 'Resolved') ? 'NOW()' : 'NULL';
        $stmt = $this->db->prepare(
            "UPDATE inquiries
             SET status = ?, resolved_at = $resolvedAt, updated_at = NOW()
             WHERE inquiry_id = ?"
        );

        $stmt->bind_param('si', $status, $inquiryId);
        $stmt->execute();
    }

    public function assignStaff(int $inquiryId, int $staffId): bool
    {
        $this->db->begin_transaction();
        try {
            $personQuery = $this->db->prepare('SELECT role, is_active, office_id FROM users WHERE user_id = ? FOR UPDATE');
            $personQuery->bind_param('i', $staffId);
            $personQuery->execute();
            $person = $personQuery->get_result()->fetch_assoc();
            $inquiry = $this->lockInquiry($inquiryId);
            if (
                !$person || !(int) $person['is_active'] || !in_array($person['role'], ['staff', 'admin'], true)
                || ($person['role'] === 'staff' && (int) $person['office_id'] !== (int) $inquiry['office_id'])
                || $inquiry['status'] === 'Resolved'
            ) {
                throw new ResponseNotAllowedException('This concern is no longer available for assignment.');
            }
            $stmt = $this->db->prepare(
                'UPDATE inquiries
                 SET assigned_staff_id = ?, status = "In Progress", updated_at = NOW()
                 WHERE inquiry_id = ?'
            );

            $stmt->bind_param('ii', $staffId, $inquiryId);
            $stmt->execute();
            if ($inquiry['status'] !== 'In Progress') {
                $inquiry['status'] = 'In Progress';
                (new NotificationService())->status($inquiry);
            }
            $this->db->commit();
            return true;
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    public function overridePriority(int $inquiryId, ?string $priority, int $staffId): bool
    {
        if ($priority === null) {
            $stmt = $this->db->prepare(
                'UPDATE inquiries
                 SET priority_override = NULL, priority_override_by = NULL, priority_override_at = NULL
                 WHERE inquiry_id = ?'
            );
            $stmt->bind_param('i', $inquiryId);
        } else {
            $stmt = $this->db->prepare(
                'UPDATE inquiries
                 SET priority_override = ?, priority_override_by = ?, priority_override_at = NOW()
                 WHERE inquiry_id = ?'
            );
            $stmt->bind_param('sii', $priority, $staffId, $inquiryId);
        }

        if (!$stmt->execute()) {
            error_log('Failed to override inquiry urgency: ' . $stmt->error);
            throw new Exception('Failed to update inquiry urgency.');
        }

        return $stmt->affected_rows > 0;
    }

    public function addResponse(int $inquiryId, int $staffId, string $message, ?string $status = null): int
    {
        if ($status !== null && !in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Invalid inquiry status.');
        }
        $this->db->begin_transaction();
        try {
            $inquiry = $this->lockInquiry($inquiryId);
            $actorQuery = $this->db->prepare('SELECT user_id, role, office_id, is_active FROM users WHERE user_id = ?');
            $actorQuery->bind_param('i', $staffId);
            $actorQuery->execute();
            $actor = $actorQuery->get_result()->fetch_assoc();
            if (
                !$actor || !(int) $actor['is_active']
                || !in_array($actor['role'], ['student', 'staff', 'admin'], true)
                || ($actor['role'] === 'student' && ((int) $inquiry['student_id'] !== $staffId || $inquiry['status'] !== 'On Hold' || $status !== null))
                || ($actor['role'] === 'staff' && (empty($actor['office_id']) || (int) $actor['office_id'] !== (int) $inquiry['office_id']))
            ) {
                throw new ResponseNotAllowedException('This concern is no longer available for your reply.');
            }
            $stmt = $this->db->prepare(
                'INSERT INTO inquiry_responses (inquiry_id, staff_id, message, created_at)
                 VALUES (?, ?, ?, NOW())'
            );

            $stmt->bind_param('iis', $inquiryId, $staffId, $message);

            if (!$stmt->execute()) {
                error_log('Failed to add response: ' . $stmt->error);
                throw new Exception('Failed to add response');
            }

            $responseId = $stmt->insert_id;
            if ($status !== null && $status !== $inquiry['status']) {
                $this->writeStatus($inquiryId, $status);
                $inquiry['status'] = $status;
            }
            (new NotificationService())->response($inquiry, $responseId, $actor);
            $this->db->commit();
            return $responseId;
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    public function getReplies(int $inquiryId): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, u.role AS sender_role, CONCAT(u.first_name, " ", u.last_name) as staff_name
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
                    SUM(CASE WHEN status = "On Hold" THEN 1 ELSE 0 END) as on_hold,
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
        $inquiry = $result->fetch_assoc();
        return $inquiry === null ? null : StudentProfile::concernSummary($inquiry);
    }

    public function attachAiResult(int $inquiryId, ?array $match): void
    {
        $status = $match ? 'Resolved' : 'Pending';
        $this->updateStatus($inquiryId, $status);
    }
}
