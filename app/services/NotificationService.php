<?php
require_once __DIR__ . '/../models/Notification.php';

class NotificationService
{
    private mysqli $db;
    private Notification $notifications;

    public function __construct()
    {
        $this->db = getDbConnection();
        $this->notifications = new Notification();
    }

    private function officeRecipients(?int $officeId, int $exclude = 0): array
    {
        $stmt = $this->db->prepare('SELECT user_id FROM users WHERE is_active = 1 AND user_id <> ?
            AND (role = "admin" OR (role = "staff" AND office_id = ?))');
        $stmt->bind_param('ii', $exclude, $officeId);
        $stmt->execute();
        return array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'user_id'));
    }

    private function studentRecipients(int $studentId): array
    {
        $stmt = $this->db->prepare('SELECT user_id FROM users WHERE user_id = ? AND is_active = 1');
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ? [$studentId] : [];
    }

    private function publish(array $recipients, array $inquiry, string $title, string $message, string $key): void
    {
        foreach (array_unique($recipients) as $id) {
            $this->notifications->record($id, (int)$inquiry['inquiry_id'], $title, $message, $key);
        }
    }

    public function created(array $inquiry): void
    {
        $id = (int)$inquiry['inquiry_id'];
        $priority = $inquiry['ai_priority'] ?? 'Needs triage';
        $this->publish($this->studentRecipients((int)$inquiry['student_id']), $inquiry, 'Concern received',
            "Concern INQ-$id has been received. Track its status in My Concerns.", "created:$id:student");
        $this->publish($this->officeRecipients($inquiry['office_id'] === null ? null : (int)$inquiry['office_id']), $inquiry,
            'New concern', "Concern INQ-$id is ready for review. Urgency: $priority.", "created:$id:staff");
    }

    public function response(array $inquiry, int $responseId, array $actor): void
    {
        $id = (int)$inquiry['inquiry_id'];
        $studentReply = $actor['role'] === 'student';
        $recipients = $studentReply ? $this->officeRecipients((int)$inquiry['office_id'], (int)$actor['user_id'])
            : $this->studentRecipients((int)$inquiry['student_id']);
        $title = $studentReply ? 'Student replied' : 'Staff replied';
        $message = $studentReply ? "New information was added to concern INQ-$id. Sign in to read the reply."
            : "Your support team replied to concern INQ-$id. Status: {$inquiry['status']}. Sign in to read the reply.";
        $this->publish($recipients, $inquiry, $title, $message, "response:$responseId");
    }

    public function status(array $inquiry): void
    {
        $id = (int)$inquiry['inquiry_id'];
        $this->publish($this->studentRecipients((int)$inquiry['student_id']), $inquiry, 'Concern status updated',
            "Concern INQ-$id is now {$inquiry['status']}.", 'status:' . $id . ':' . bin2hex(random_bytes(8)));
    }

    public function assigned(array $inquiry, int $staffId): void
    {
        $id = (int)$inquiry['inquiry_id'];
        $this->publish([$staffId], $inquiry, 'Concern assigned to you',
            "Concern INQ-$id has been assigned to you. Sign in to review it.", 'assigned:' . $id . ':' . bin2hex(random_bytes(8)));
    }
}
