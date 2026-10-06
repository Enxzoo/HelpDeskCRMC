<?php
require_once __DIR__ . '/AdminWorkspace.php';

class AdminReport
{
    public static function filters(array $input): array
    {
        $type = AdminWorkspace::text($input, 'type', 20);
        if (!in_array($type, ['inquiries', 'concerns'], true))
            throw new AdminRequestException('Invalid report type.');
        $dates = [];
        foreach (['from', 'to'] as $key) {
            $value = AdminWorkspace::text($input, $key, 10);
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value || $value < '2000-01-01')
                throw new AdminRequestException('Please select valid report dates.');
            $dates[$key] = $value;
        }
        if ($dates['from'] > $dates['to'])
            throw new AdminRequestException('The end date must be on or after the start date.');
        $office = ($input['office_id'] ?? '') === '' ? null : AdminWorkspace::id($input, 'office_id');
        $status = AdminWorkspace::text($input, 'status', 20, false);
        $category = AdminWorkspace::text($input, 'category', 100, false);
        $program = ($input['program_id'] ?? '') === '' ? null : AdminWorkspace::id($input, 'program_id');
        if ($status !== '' && !in_array($status, Inquiry::STATUSES, true))
            throw new AdminRequestException('Invalid report status.');
        if ($type === 'inquiries' && ($office !== null || $status !== ''))
            throw new AdminRequestException('Office and status filters apply to concern reports only.');
        if ($type === 'concerns' && $category !== '')
            throw new AdminRequestException('Category filters apply to inquiry reports only.');
        return ['type' => $type, 'from' => $dates['from'], 'to' => $dates['to'], 'office_id' => $office, 'status' => $status, 'category' => $category, 'program_id' => $program];
    }

    public static function headers(string $type): array
    {
        return $type === 'inquiries' ? ['Inquiry ID', 'Student', 'Student Number', 'Category', 'Started', 'Last Activity', 'Current College', 'Current Program', 'Current Year Level', 'Current Section', 'Current Academic Term']
            : ['Concern ID', 'Subject', 'Student', 'Student Number', 'Office', 'Status', 'Priority', 'Assigned Staff', 'Received', 'Resolved', 'College at Submission', 'Program at Submission', 'Year Level at Submission', 'Section at Submission', 'Academic Term at Submission'];
    }

    public function rows(array $filters): mysqli_result
    {
        $db = getDbConnection();
        $end = (new DateTimeImmutable($filters['to']))->modify('+1 day')->format('Y-m-d');
        $values = [$filters['from'], $end];
        $types = 'ss';
        if ($filters['type'] === 'inquiries') {
            // Reports use conversation metadata, never the student's private chat transcript.
            $sql = 'SELECT CONCAT("CHAT-", c.session_id) AS id, CONCAT(u.first_name, " ", u.last_name) AS student,
                u.student_number, c.category, c.created_at, c.updated_at, sc.name, sp.name, p.year_level, p.section,
                CONCAT(t.academic_year, " / ", t.semester) FROM chat_sessions c
                JOIN users u ON u.user_id = c.student_id LEFT JOIN student_profiles p ON p.user_id = u.user_id
                LEFT JOIN school_programs sp ON sp.program_id = p.program_id LEFT JOIN school_colleges sc ON sc.college_id = sp.college_id
                LEFT JOIN school_terms t ON t.term_id = p.term_id WHERE c.created_at >= ? AND c.created_at < ?';
            if ($filters['category'] !== '') {
                $sql .= ' AND c.category = ?';
                $types .= 's';
                $values[] = $filters['category'];
            }
            if (($filters['program_id'] ?? null) !== null) {
                $sql .= ' AND p.program_id = ?';
                $types .= 'i';
                $values[] = $filters['program_id'];
            }
            $sql .= ' ORDER BY c.created_at DESC, c.session_id DESC';
        } else {
            $sql = 'SELECT CONCAT("INQ-", i.inquiry_id) AS id, i.subject, CONCAT(u.first_name, " ", u.last_name) AS student,
                u.student_number, o.office_name, i.status, COALESCE(i.priority_override, i.ai_priority, "Needs triage") AS priority,
                CONCAT(s.first_name, " ", s.last_name) AS staff, i.created_at, i.resolved_at,
                JSON_UNQUOTE(JSON_EXTRACT(i.student_profile_snapshot, "$.college_name")),
                JSON_UNQUOTE(JSON_EXTRACT(i.student_profile_snapshot, "$.program_name")),
                JSON_UNQUOTE(JSON_EXTRACT(i.student_profile_snapshot, "$.year_level")),
                JSON_UNQUOTE(JSON_EXTRACT(i.student_profile_snapshot, "$.section")),
                CONCAT(JSON_UNQUOTE(JSON_EXTRACT(i.student_profile_snapshot, "$.academic_year")), " / ",
                    JSON_UNQUOTE(JSON_EXTRACT(i.student_profile_snapshot, "$.semester")))
                FROM inquiries i JOIN users u ON u.user_id = i.student_id LEFT JOIN offices o ON o.office_id = i.office_id
                LEFT JOIN users s ON s.user_id = i.assigned_staff_id WHERE i.created_at >= ? AND i.created_at < ?';
            if ($filters['office_id'] !== null) {
                $sql .= ' AND i.office_id = ?';
                $types .= 'i';
                $values[] = $filters['office_id'];
            }
            if ($filters['status'] !== '') {
                $sql .= ' AND i.status = ?';
                $types .= 's';
                $values[] = $filters['status'];
            }
            if (($filters['program_id'] ?? null) !== null) {
                $sql .= ' AND JSON_EXTRACT(i.student_profile_snapshot, "$.program_id") = ?';
                $types .= 'i';
                $values[] = $filters['program_id'];
            }
            $sql .= ' ORDER BY i.created_at DESC, i.inquiry_id DESC';
        }
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        return $stmt->get_result();
    }

    public static function csvCell(mixed $value): string
    {
        $text = (string) ($value ?? '');
        return preg_match('/^\s*[=+\-@\t\r\n]/u', $text) ? "'" . $text : $text;
    }
}
