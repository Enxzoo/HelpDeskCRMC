<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/Inquiry.php';

class AdminRequestException extends RuntimeException
{
    public function __construct(string $message, public int $status = 400)
    {
        parent::__construct($message);
    }
}

class AdminWorkspace
{
    private mysqli $db;

    public function __construct()
    {
        $this->db = getDbConnection();
    }

    public static function text(array $input, string $key, int $max, bool $required = true): string
    {
        $value = $input[$key] ?? '';
        if (!is_string($value) || mb_strlen($value) > $max || ($required && trim($value) === '')) {
            throw new AdminRequestException('Please enter a valid ' . str_replace('_', ' ', $key) . '.');
        }
        return trim($value);
    }

    public static function id(array $input, string $key): int
    {
        $value = $input[$key] ?? null;
        if (
            (!is_int($value) && !is_string($value)) || !ctype_digit((string) $value)
            || strlen((string) $value) > 10 || (int) $value < 1 || (int) $value > 4294967295
        ) {
            throw new AdminRequestException('Invalid ' . str_replace('_', ' ', $key) . '.');
        }
        return (int) $value;
    }

    private function query(string $sql, string $types = '', array $values = []): mysqli_result
    {
        $stmt = $this->db->prepare($sql);
        if ($types !== '')
            $stmt->bind_param($types, ...$values);
        $stmt->execute();
        return $stmt->get_result();
    }

    private function activeOffice(int $id): void
    {
        if (!$this->query('SELECT office_id FROM offices WHERE office_id = ? AND is_active = 1', 'i', [$id])->fetch_assoc()) {
            throw new AdminRequestException('Please select an active office.');
        }
    }

    private function lockStaff(int $id): array
    {
        $staff = $this->query('SELECT * FROM users WHERE user_id = ? AND role = "staff" AND deleted_at IS NULL FOR UPDATE', 'i', [$id])->fetch_assoc();
        if (!$staff)
            throw new AdminRequestException('Staff account not found.', 404);
        return $staff;
    }

    public function staff(): array
    {
        return $this->db->query('SELECT u.user_id, u.first_name, u.last_name, u.email, u.office_id,
            u.is_active, u.last_login_at, u.created_at, o.office_name,
            (SELECT COUNT(*) FROM inquiries i WHERE i.assigned_staff_id = u.user_id AND i.status <> "Resolved") AS open_count
            FROM users u LEFT JOIN offices o ON o.office_id = u.office_id
            WHERE u.role = "staff" AND u.deleted_at IS NULL ORDER BY u.first_name, u.last_name, u.user_id')->fetch_all(MYSQLI_ASSOC);
    }

    public function saveStaff(array $input, bool $create): int
    {
        $first = self::text($input, 'first_name', 100);
        $last = self::text($input, 'last_name', 100);
        $email = self::text($input, 'email', 150);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            throw new AdminRequestException('Please enter a valid email address.');
        $office = self::id($input, 'office_id');
        $this->activeOffice($office);
        $password = $input['password'] ?? '';
        if (!is_string($password) || strlen($password) > 72 || (($create || $password !== '') && strlen($password) < 8)) {
            throw new AdminRequestException('Password must contain 8 to 72 bytes.');
        }
        $active = $input['is_active'] ?? true;
        if (!is_bool($active))
            throw new AdminRequestException('Invalid account status.');
        $active = (int) $active;
        $this->db->begin_transaction();
        try {
            if ($create) {
                $id = (new User())->create([
                    'role' => 'staff',
                    'office_id' => $office,
                    'first_name' => $first,
                    'last_name' => $last,
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT)
                ]);
            } else {
                $id = self::id($input, 'user_id');
                $staff = $this->lockStaff($id);
                if (!$active || (int) $staff['office_id'] !== $office)
                    $this->releaseAssignments($id);
                $stmt = $this->db->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, office_id = ? WHERE user_id = ?');
                $stmt->bind_param('sssii', $first, $last, $email, $office, $id);
                $stmt->execute();
                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $this->db->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
                    $stmt->bind_param('si', $hash, $id);
                    $stmt->execute();
                }
            }
            $stmt = $this->db->prepare('UPDATE users SET is_active = ? WHERE user_id = ?');
            $stmt->bind_param('ii', $active, $id);
            $stmt->execute();
            $this->db->commit();
            return $id;
        } catch (Throwable $error) {
            $this->db->rollback();
            throw $error;
        }
    }

    private function releaseAssignments(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE inquiries SET assigned_staff_id = NULL
            WHERE assigned_staff_id = ? AND status <> "Resolved"');
        $stmt->bind_param('i', $id);
        $stmt->execute();
    }

    public function deleteStaff(int $id): void
    {
        $this->db->begin_transaction();
        try {
            $this->lockStaff($id);
            $this->releaseAssignments($id);
            $stmt = $this->db->prepare('UPDATE users SET is_active = 0, deleted_at = NOW() WHERE user_id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $this->db->commit();
        } catch (Throwable $error) {
            $this->db->rollback();
            throw $error;
        }
    }

    public function knowledge(): array
    {
        return $this->db->query('SELECT k.*, o.office_name, CONCAT(u.first_name, " ", u.last_name) AS editor_name
            FROM knowledge_entries k LEFT JOIN offices o ON o.office_id = k.office_id
            JOIN users u ON u.user_id = k.updated_by ORDER BY k.updated_at DESC, k.entry_id DESC')->fetch_all(MYSQLI_ASSOC);
    }

    public function saveKnowledge(array $input, int $actor, bool $create): int
    {
        $title = self::text($input, 'title', 150);
        $content = self::text($input, 'content', 6000);
        $status = self::text($input, 'status', 20);
        if (!in_array($status, ['Draft', 'Published'], true))
            throw new AdminRequestException('Invalid publishing status.');
        $office = ($input['office_id'] ?? '') === '' || ($input['office_id'] ?? null) === null ? null : self::id($input, 'office_id');
        if ($office !== null)
            $this->activeOffice($office);
        if ($create) {
            $stmt = $this->db->prepare('INSERT INTO knowledge_entries (title, content, status, office_id, updated_by) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('sssii', $title, $content, $status, $office, $actor);
        } else {
            $id = self::id($input, 'entry_id');
            if (!$this->query('SELECT entry_id FROM knowledge_entries WHERE entry_id = ?', 'i', [$id])->fetch_assoc()) {
                throw new AdminRequestException('Knowledge entry not found.', 404);
            }
            $stmt = $this->db->prepare('UPDATE knowledge_entries SET title = ?, content = ?, status = ?, office_id = ?, updated_by = ? WHERE entry_id = ?');
            $stmt->bind_param('sssiii', $title, $content, $status, $office, $actor, $id);
        }
        $stmt->execute();
        return $create ? (int) $stmt->insert_id : $id;
    }

    public function deleteKnowledge(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM knowledge_entries WHERE entry_id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        if (!$stmt->affected_rows)
            throw new AdminRequestException('Knowledge entry not found.', 404);
    }

    public static function knowledgeSearchTerms(string $question): string
    {
        $aliases = [
            'bayad|mobayad|mubayad|pagbayad|gibayad|kwarta' => 'payment tuition cashier balance',
            'grado|grades|marka|hagbong' => 'grades academic records',
            'iskolar|scholar|scholarship' => 'scholarship financial assistance',
            'enrol|enroll|enrolment|paenrol|pa-enrol|magpaenrol' => 'enrollment registration admission',
            'tor|transcript' => 'transcript records request processing',
            'dokumento|papeles' => 'documents certificate requirements',
            'libro|library|librohan' => 'library books borrowing clearance',
            'nawala|nawagtang' => 'lost replacement',
            'login|log-in|password|pasword' => 'portal login password access reset',
            'oras|kanus-a|kanusa|adlaw|dugay' => 'hours schedule processing time',
        ];
        $terms = [mb_substr($question, 0, 4000)];
        foreach ($aliases as $pattern => $english) {
            if (preg_match('/(?<![\p{L}\p{N}_])(?:' . $pattern . ')(?![\p{L}\p{N}_])/ui', $question)) $terms[] = $english;
        }
        return implode(' ', $terms);
    }

    public function publishedKnowledge(string $question): array
    {
        $terms = self::knowledgeSearchTerms($question);
        $entries = $this->query('SELECT title, content, o.office_name FROM knowledge_entries k
            LEFT JOIN offices o ON o.office_id = k.office_id WHERE k.status = "Published"
            AND (k.office_id IS NULL OR o.is_active = 1)
            AND MATCH(k.title, k.content) AGAINST (? IN NATURAL LANGUAGE MODE) > 0
            ORDER BY MATCH(k.title, k.content) AGAINST (? IN NATURAL LANGUAGE MODE) DESC, k.entry_id DESC LIMIT 4',
            'ss',
            [$terms, $terms]
        )->fetch_all(MYSQLI_ASSOC);
        $keywords = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($terms), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($entries as &$entry) {
            if (mb_strlen($entry['content']) <= 1800) continue;
            $best = $score = 0;
            foreach (array_unique($keywords) as $keyword) {
                if (mb_strlen($keyword) < 4) continue;
                $position = mb_stripos($entry['content'], $keyword);
                if ($position !== false && mb_strlen($keyword) > $score) {
                    $best = max(0, $position - 250);
                    $score = mb_strlen($keyword);
                }
            }
            $entry['content'] = ($best > 0 ? '[Excerpt] ' : '') . mb_substr($entry['content'], $best, 1750) . ' [Excerpt ends]';
        }
        return $entries;
    }

    public function assign(int $id, ?int $staff): void
    {
        $this->db->begin_transaction();
        try {
            // Lock staff first, matching account changes that release assignments.
            $person = $staff === null ? null : $this->lockStaff($staff);
            $inquiry = $this->query('SELECT * FROM inquiries WHERE inquiry_id = ? FOR UPDATE', 'i', [$id])->fetch_assoc();
            if (!$inquiry)
                throw new AdminRequestException('Concern not found.', 404);
            if ($inquiry['status'] === 'Resolved')
                throw new AdminRequestException('Resolved concerns cannot be reassigned.', 409);
            if ($person && (!(int) $person['is_active'] || (int) $person['office_id'] !== (int) $inquiry['office_id'])) {
                throw new AdminRequestException('Choose active staff from this concern\'s office.', 409);
            }
            if (($inquiry['assigned_staff_id'] === null ? null : (int) $inquiry['assigned_staff_id']) !== $staff) {
                $status = $inquiry['status'] === 'Pending' && $staff !== null ? 'In Progress' : $inquiry['status'];
                $stmt = $this->db->prepare('UPDATE inquiries SET assigned_staff_id = ?, status = ? WHERE inquiry_id = ?');
                $stmt->bind_param('isi', $staff, $status, $id);
                $stmt->execute();
                if ($status !== $inquiry['status']) {
                    $inquiry['status'] = $status;
                    (new NotificationService())->status($inquiry);
                }
                if ($staff !== null)
                    (new NotificationService())->assigned($inquiry, $staff);
            }
            $this->db->commit();
        } catch (Throwable $error) {
            $this->db->rollback();
            throw $error;
        }
    }

    public function overview(): array
    {
        $stats = (new Inquiry())->getStats();
        $stats['unassigned'] = (int) $this->db->query('SELECT COUNT(*) AS n FROM inquiries WHERE assigned_staff_id IS NULL AND status <> "Resolved"')->fetch_assoc()['n'];
        $stats['active_staff'] = (int) $this->db->query('SELECT COUNT(*) AS n FROM users WHERE role = "staff" AND is_active = 1 AND deleted_at IS NULL')->fetch_assoc()['n'];
        $stats['inquiries'] = (int) $this->db->query('SELECT COUNT(*) AS n FROM chat_sessions')->fetch_assoc()['n'];
        $stats['published'] = (int) $this->db->query('SELECT COUNT(*) AS n FROM knowledge_entries WHERE status = "Published"')->fetch_assoc()['n'];
        $offices = $this->db->query('SELECT o.office_name, o.office_id,
            COUNT(i.inquiry_id) AS total, COALESCE(SUM(i.status <> "Resolved"), 0) AS open_count,
            COALESCE(SUM(i.assigned_staff_id IS NULL AND i.status <> "Resolved"), 0) AS unassigned
            FROM offices o LEFT JOIN inquiries i ON i.office_id = o.office_id
            WHERE o.is_active = 1 GROUP BY o.office_id ORDER BY open_count DESC, o.office_name')->fetch_all(MYSQLI_ASSOC);
        return [
            'stats' => array_map('intval', $stats),
            'workload' => $offices,
            'unassigned' => $this->concerns(['assignment' => 'unassigned'])['items']
        ];
    }

    public function concerns(array $filters): array
    {
        $where = '1=1';
        $types = '';
        $values = [];
        if (($filters['office_id'] ?? '') !== '') {
            $where .= ' AND i.office_id = ?';
            $types .= 'i';
            $values[] = self::id($filters, 'office_id');
        }
        $status = self::text($filters, 'status', 20, false);
        if ($status !== '') {
            if (!in_array($status, Inquiry::STATUSES, true))
                throw new AdminRequestException('Invalid concern status.');
            $where .= ' AND i.status = ?';
            $types .= 's';
            $values[] = $status;
        }
        $assignment = self::text($filters, 'assignment', 20, false);
        if ($assignment !== '' && $assignment !== 'unassigned')
            throw new AdminRequestException('Invalid assignment filter.');
        if ($assignment === 'unassigned')
            $where .= ' AND i.assigned_staff_id IS NULL AND i.status <> "Resolved"';
        $search = self::text($filters, 'search', 150, false);
        if ($search !== '') {
            $where .= ' AND (i.subject LIKE ? OR CONCAT(u.first_name, " ", u.last_name) LIKE ? OR CAST(i.inquiry_id AS CHAR) = ?)';
            $types .= 'sss';
            array_push($values, '%' . $search . '%', '%' . $search . '%', preg_replace('/^INQ-/i', '', $search));
        }
        $page = isset($filters['page']) ? self::id($filters, 'page') : 1;
        $joins = ' FROM inquiries i JOIN users u ON u.user_id = i.student_id LEFT JOIN offices o ON o.office_id = i.office_id
            LEFT JOIN users s ON s.user_id = i.assigned_staff_id WHERE ' . $where;
        $total = (int) $this->query('SELECT COUNT(*) AS n' . $joins, $types, $values)->fetch_assoc()['n'];
        $page = min($page, max(1, (int) ceil($total / 20)));
        $items = $this->query('SELECT i.inquiry_id, i.subject, i.status, i.office_id, i.assigned_staff_id, i.created_at,
            COALESCE(i.priority_override, i.ai_priority, "Needs triage") AS priority,
            o.office_name, CONCAT(u.first_name, " ", u.last_name) AS student_name,
            CONCAT(s.first_name, " ", s.last_name) AS staff_name' . $joins . '
            ORDER BY (i.status = "Resolved"), FIELD(COALESCE(i.priority_override, i.ai_priority), "Critical/Urgent", "High", "Normal", "Low"), i.created_at LIMIT 20 OFFSET ?',
            $types . 'i',
            [...$values, ($page - 1) * 20]
        )->fetch_all(MYSQLI_ASSOC);
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / 20))];
    }

    public function concern(int $id): array
    {
        $model = new Inquiry();
        $row = $model->findById($id);
        if (!$row)
            throw new AdminRequestException('Concern not found.', 404);
        $row['replies'] = $model->getReplies($id);
        return $row;
    }
}
