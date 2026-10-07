<?php
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/../config/legal.php';

class StudentAccountException extends RuntimeException
{
    public function __construct(string $message, public int $status = 400)
    {
        parent::__construct($message);
    }
}

class StudentProfile
{
    private mysqli $db;

    public function __construct()
    {
        $this->db = getDbConnection();
    }

    private function query(string $sql, string $types = '', array $values = []): mysqli_stmt
    {
        $stmt = $this->db->prepare($sql);
        if ($types !== '') $stmt->bind_param($types, ...$values);
        $stmt->execute();
        return $stmt;
    }

    public static function text(array $input, string $key, int $max, bool $required = false): string
    {
        $value = $input[$key] ?? '';
        if (!is_string($value) || mb_strlen($value) > $max || ($required && trim($value) === '')) {
            throw new StudentAccountException('Please enter a valid ' . str_replace('_', ' ', $key) . '.');
        }
        return trim($value);
    }

    public static function id(array $input, string $key): int
    {
        $value = $input[$key] ?? '';
        if ((!is_int($value) && !is_string($value)) || !ctype_digit((string)$value)
            || strlen((string)$value) > 10 || (int)$value < 1 || (int)$value > 4294967295) {
            throw new StudentAccountException('Please select a valid ' . str_replace('_', ' ', $key) . '.');
        }
        return (int)$value;
    }

    public function catalog(bool $all = false): array
    {
        return [
            'colleges' => $this->query('SELECT * FROM school_colleges' . ($all ? '' : ' WHERE is_active = 1') . ' ORDER BY name')->get_result()->fetch_all(MYSQLI_ASSOC),
            'programs' => $this->query('SELECT p.*, c.name AS college_name FROM school_programs p JOIN school_colleges c ON c.college_id = p.college_id'
                . ($all ? '' : ' WHERE p.is_active = 1 AND c.is_active = 1') . ' ORDER BY p.name')->get_result()->fetch_all(MYSQLI_ASSOC),
            'terms' => $this->query('SELECT * FROM school_terms' . ($all ? '' : ' WHERE registration_open = 1') . ' ORDER BY academic_year DESC, term_id DESC')->get_result()->fetch_all(MYSQLI_ASSOC),
        ];
    }

    public function find(int $userId): ?array
    {
        return $this->query('SELECT u.user_id, u.first_name, u.last_name, u.student_number, u.email, u.is_active,
            p.middle_name, p.suffix, p.mobile_number, p.program_id, p.term_id, p.year_level, p.section,
            p.enrollment_type, p.revision, a.terms_version, a.terms_accepted_at, a.privacy_version, a.privacy_accepted_at,
            pr.code AS program_code, pr.name AS program_name, pr.college_id, c.name AS college_name,
            t.academic_year, t.semester
            FROM users u LEFT JOIN student_profiles p ON p.user_id = u.user_id
            LEFT JOIN school_programs pr ON pr.program_id = p.program_id
            LEFT JOIN school_colleges c ON c.college_id = pr.college_id
            LEFT JOIN school_terms t ON t.term_id = p.term_id
            LEFT JOIN student_legal_acceptances a ON a.user_id = u.user_id
            WHERE u.user_id = ? AND u.role = "student" AND u.deleted_at IS NULL', 'i', [$userId])->get_result()->fetch_assoc();
    }

    public function snapshot(int $userId): ?array
    {
        $profile = $this->find($userId);
        if (!$profile || !(int)$profile['is_active']) return null;
        return array_intersect_key($profile, array_flip(['student_number', 'college_id', 'program_id', 'college_name', 'program_name', 'program_code',
            'year_level', 'section', 'academic_year', 'semester', 'enrollment_type']));
    }

    public static function concernSummary(array $inquiry): array
    {
        $snapshot = json_decode($inquiry['student_profile_snapshot'] ?? 'null', true);
        foreach (['program_name' => 'student_program', 'college_name' => 'student_college', 'year_level' => 'student_year_level',
            'section' => 'student_section', 'academic_year' => 'student_academic_year', 'semester' => 'student_semester'] as $key => $output) {
            $inquiry[$output] = is_array($snapshot) ? ($snapshot[$key] ?? null) : null;
        }
        if (is_array($snapshot) && isset($snapshot['student_number'])) $inquiry['student_number'] = $snapshot['student_number'];
        unset($inquiry['student_profile_snapshot']);
        return $inquiry;
    }

    private function validate(array $input, bool $registration): array
    {
        $data = [];
        foreach (['first_name' => 100, 'last_name' => 100, 'student_number' => 30, 'email' => 150,
            'middle_name' => 100, 'suffix' => 20, 'mobile_number' => 25, 'section' => 50] as $key => $max) {
            $data[$key] = self::text($input, $key, $max, in_array($key, ['first_name', 'last_name', 'student_number', 'email'], true));
        }
        $data['student_number'] = mb_strtoupper($data['student_number']);
        $data['email'] = mb_strtolower($data['email']);
        if (!preg_match('/^[A-Z0-9-]+$/', $data['student_number'])) throw new StudentAccountException('Student ID may contain letters, numbers, and hyphens.');
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) throw new StudentAccountException('Please enter a valid email address.');
        if ($data['mobile_number'] !== '' && !preg_match('/^\+?[0-9 ()-]{7,25}$/', $data['mobile_number'])) {
            throw new StudentAccountException('Please enter a valid mobile number.');
        }
        $data['program_id'] = self::id($input, 'program_id');
        $collegeId = self::id($input, 'college_id');
        $program = $this->query('SELECT pr.* FROM school_programs pr JOIN school_colleges c ON c.college_id = pr.college_id
            WHERE pr.program_id = ? AND pr.college_id = ? AND pr.is_active = 1 AND c.is_active = 1', 'ii', [$data['program_id'], $collegeId])->get_result()->fetch_assoc();
        if (!$program) throw new StudentAccountException('Please select an available program belonging to your college.');
        $data['year_level'] = self::id($input, 'year_level');
        if ($data['year_level'] > (int)$program['max_year_level']) throw new StudentAccountException('Year level is not valid for this program.');
        $data['term_id'] = self::id($input, 'term_id');
        if (!$this->query('SELECT term_id FROM school_terms WHERE term_id = ? AND registration_open = 1', 'i', [$data['term_id']])->get_result()->fetch_assoc()) {
            throw new StudentAccountException('Please select an open academic term.');
        }
        $data['enrollment_type'] = self::text($input, 'enrollment_type', 20, true);
        if (!in_array($data['enrollment_type'], ['Regular', 'Irregular', 'Transferee', 'Returning'], true)) throw new StudentAccountException('Please select a valid enrollment classification.');
        if ($registration) {
            if (($input['terms_accepted'] ?? '') !== '1') throw new StudentAccountException('You must agree to the Terms of Service before creating an account.');
            if (($input['privacy_accepted'] ?? '') !== '1') throw new StudentAccountException('You must read the Privacy Policy and consent to the stated account and helpdesk processing before creating an account.');
            if (($input['terms_version'] ?? '') !== HELPDESK_TERMS_VERSION || ($input['privacy_version'] ?? '') !== HELPDESK_PRIVACY_VERSION) {
                throw new StudentAccountException('The legal documents have changed or their versions are missing. Read the current Terms of Service and Privacy Policy and accept them again.');
            }
            $password = $input['password'] ?? null;
            if (!is_string($password) || strlen($password) < 8 || strlen($password) > 72) throw new StudentAccountException('Password must contain 8 to 72 bytes.');
            if (!is_string($input['confirm_password'] ?? null) || $password !== $input['confirm_password']) throw new StudentAccountException('Password confirmation does not match.');
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        return $data;
    }

    private function saveDetails(int $userId, array $data): void
    {
        $this->query('INSERT INTO student_profiles (user_id, middle_name, suffix, mobile_number, program_id, term_id,
            year_level, section, enrollment_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE middle_name = VALUES(middle_name), suffix = VALUES(suffix), mobile_number = VALUES(mobile_number),
            program_id = VALUES(program_id), term_id = VALUES(term_id), year_level = VALUES(year_level), section = VALUES(section),
            enrollment_type = VALUES(enrollment_type), revision = revision + 1',
            'isssiiiss', [$userId, $data['middle_name'], $data['suffix'], $data['mobile_number'], $data['program_id'],
                $data['term_id'], $data['year_level'], $data['section'], $data['enrollment_type']]);
    }

    private function event(int $userId, int $actorId, string $action, string $reason = ''): void
    {
        $this->query('INSERT INTO student_account_events (student_id, actor_id, action, reason) VALUES (?, ?, ?, ?)', 'iiss', [$userId, $actorId, $action, $reason]);
    }

    public function register(array $input): int
    {
        $data = $this->validate($input, true);
        $this->db->begin_transaction();
        try {
            $userId = (new User())->create($data + ['role' => 'student', 'office_id' => null]);
            $this->saveDetails($userId, $data);
            $this->query('INSERT INTO student_legal_acceptances (user_id, terms_version, terms_accepted_at, privacy_version, privacy_accepted_at)
                VALUES (?, ?, NOW(), ?, NOW())', 'iss', [$userId, HELPDESK_TERMS_VERSION, HELPDESK_PRIVACY_VERSION]);
            $this->event($userId, $userId, 'Account created');
            $this->db->commit();
            return $userId;
        } catch (Throwable $error) {
            $this->db->rollback();
            throw $error;
        }
    }

    public function updateDetails(int $userId, array $input): void
    {
        $data = $this->validate($input, false);
        $this->db->begin_transaction();
        try {
            $this->lockStudent($userId);
            $user = (new User())->findById($userId);
            if ($data['email'] !== $user['email'] && (!is_string($input['current_password'] ?? null)
                || !password_verify($input['current_password'], $user['password_hash']))) {
                throw new StudentAccountException('Enter your current password to change the email address.');
            }
            $this->query('UPDATE users SET first_name = ?, last_name = ?, student_number = ?, email = ? WHERE user_id = ?',
                'ssssi', [$data['first_name'], $data['last_name'], $data['student_number'], $data['email'], $userId]);
            $this->saveDetails($userId, $data);
            $this->event($userId, $userId, 'Profile updated');
            $this->db->commit();
        } catch (Throwable $error) { $this->db->rollback(); throw $error; }
    }

    private function lockStudent(int $userId): void
    {
        $user = $this->query('SELECT user_id, is_active FROM users WHERE user_id = ? AND role = "student" AND deleted_at IS NULL FOR UPDATE', 'i', [$userId])->get_result()->fetch_assoc();
        if (!$user) throw new StudentAccountException('Student account not found.', 404);
        if (!(int)$user['is_active']) throw new StudentAccountException('Student account is suspended.', 409);
    }

    private function requireAdmin(int $actorId): void
    {
        if (!$this->query('SELECT user_id FROM users WHERE user_id = ? AND role = "admin" AND is_active = 1 AND deleted_at IS NULL', 'i', [$actorId])->get_result()->fetch_assoc()) {
            throw new StudentAccountException('Account management requires an administrator.', 403);
        }
    }

    public function updateContact(int $userId, array $input): void
    {
        $mobile = self::text($input, 'mobile_number', 25);
        if ($mobile !== '' && !preg_match('/^\+?[0-9 ()-]{7,25}$/', $mobile)) throw new StudentAccountException('Please enter a valid mobile number.');
        $this->db->begin_transaction();
        try {
            $this->lockStudent($userId);
            $this->query('INSERT INTO student_profiles (user_id, mobile_number) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE mobile_number = VALUES(mobile_number)', 'is', [$userId, $mobile]);
            $this->event($userId, $userId, 'Contact updated');
            $this->db->commit();
        } catch (Throwable $error) { $this->db->rollback(); throw $error; }
    }

    public function updateAcademic(int $actorId, array $input): void
    {
        $this->requireAdmin($actorId);
        $userId = self::id($input, 'user_id');
        $revision = self::id($input, 'revision');
        $reason = self::text($input, 'reason', 1000, true);
        $this->db->begin_transaction();
        try {
            $this->lockStudent($userId);
            $profile = $this->query('SELECT * FROM student_profiles WHERE user_id = ? FOR UPDATE', 'i', [$userId])->get_result()->fetch_assoc();
            if (!$profile || (int)$profile['revision'] !== $revision) {
                throw new StudentAccountException('This profile changed. Reload it before updating.', 409);
            }
            $values = $this->find($userId);
            foreach (['college_id', 'program_id', 'term_id', 'year_level', 'section', 'enrollment_type'] as $key) $values[$key] = $input[$key] ?? '';
            $data = $this->validate($values, false);
            $this->query('UPDATE student_profiles SET program_id = ?, term_id = ?, year_level = ?, section = ?,
                enrollment_type = ?, revision = revision + 1 WHERE user_id = ?', 'iiissi', [$data['program_id'],
                    $data['term_id'], $data['year_level'], $data['section'], $data['enrollment_type'], $userId]);
            $this->event($userId, $actorId, 'Academic details updated', $reason);
            $this->db->commit();
        } catch (Throwable $error) { $this->db->rollback(); throw $error; }
    }

    public function setAccess(int $actorId, array $input): void
    {
        $this->requireAdmin($actorId);
        $userId = self::id($input, 'user_id');
        $reason = self::text($input, 'reason', 1000, true);
        $active = $input['is_active'] ?? null;
        if (!in_array($active, ['0', '1'], true)) throw new StudentAccountException('Invalid account access state.');
        $this->db->begin_transaction();
        try {
            if (!$this->query('SELECT user_id FROM users WHERE user_id = ? AND role = "student" AND deleted_at IS NULL FOR UPDATE', 'i', [$userId])->get_result()->fetch_assoc()) throw new StudentAccountException('Student account not found.', 404);
            $this->query('UPDATE users SET is_active = ? WHERE user_id = ?', 'ii', [(int)$active, $userId]);
            $this->event($userId, $actorId, $active === '1' ? 'Access reinstated' : 'Access suspended', $reason);
            $this->db->commit();
        } catch (Throwable $error) { $this->db->rollback(); throw $error; }
    }

    public function students(string $search = '', string $status = '', int $page = 1): array
    {
        if ($status !== '' && !in_array($status, ['Enabled', 'Suspended'], true)) throw new StudentAccountException('Invalid access filter.');
        $like = '%' . $search . '%';
        $where = ' FROM users u LEFT JOIN student_profiles p ON p.user_id = u.user_id LEFT JOIN school_programs pr ON pr.program_id = p.program_id
            WHERE u.role = "student" AND u.deleted_at IS NULL AND (CONCAT(u.first_name, " ", u.last_name) LIKE ? OR u.student_number LIKE ? OR u.email LIKE ?)
            AND (? = "" OR IF(u.is_active = 1, "Enabled", "Suspended") = ?)';
        $params = [$like, $like, $like, $status, $status];
        $total = (int)$this->query('SELECT COUNT(*) AS total' . $where, 'sssss', $params)->get_result()->fetch_assoc()['total'];
        $pages = max(1, (int)ceil($total / 20));
        $page = max(1, min($pages, $page));
        $offset = ($page - 1) * 20;
        $items = $this->query('SELECT u.user_id, u.first_name, u.last_name, u.student_number, u.email, u.is_active,
            p.year_level, pr.name AS program_name
            ' . $where . ' ORDER BY u.user_id DESC LIMIT 20 OFFSET ' . $offset,
            'sssss', $params)->get_result()->fetch_all(MYSQLI_ASSOC);
        return compact('items', 'total', 'pages', 'page');
    }

    public function history(int $userId): array
    {
        return $this->query('SELECT e.action, e.reason, e.created_at, CONCAT(u.first_name, " ", u.last_name) AS actor_name
            FROM student_account_events e JOIN users u ON u.user_id = e.actor_id WHERE e.student_id = ? ORDER BY event_id DESC LIMIT 30', 'i', [$userId])->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function saveCatalog(int $actorId, array $input): void
    {
        $this->requireAdmin($actorId);
        $kind = self::text($input, 'kind', 20, true);
        $id = ($input['catalog_id'] ?? '') === '' ? null : self::id($input, 'catalog_id');
        if ($kind === 'college') {
            $code = mb_strtoupper(self::text($input, 'code', 20, true));
            $name = self::text($input, 'name', 150, true);
            $active = ($input['is_active'] ?? '') === '1' ? 1 : 0;
            if ($id) $this->query('UPDATE school_colleges SET code = ?, name = ?, is_active = ? WHERE college_id = ?', 'ssii', [$code, $name, $active, $id]);
            else $this->query('INSERT INTO school_colleges (code, name, is_active) VALUES (?, ?, ?)', 'ssi', [$code, $name, $active]);
        } elseif ($kind === 'program') {
            $college = self::id($input, 'college_id');
            if (!$this->query('SELECT college_id FROM school_colleges WHERE college_id = ?', 'i', [$college])->get_result()->fetch_assoc()) throw new StudentAccountException('College not found.');
            $code = mb_strtoupper(self::text($input, 'code', 30, true));
            $name = self::text($input, 'name', 200, true);
            $years = self::id($input, 'max_year_level');
            if ($years > 8) throw new StudentAccountException('Maximum year level must be between 1 and 8.');
            $active = ($input['is_active'] ?? '') === '1' ? 1 : 0;
            if ($id) $this->query('UPDATE school_programs SET college_id = ?, code = ?, name = ?, max_year_level = ?, is_active = ? WHERE program_id = ?', 'issiii', [$college, $code, $name, $years, $active, $id]);
            else $this->query('INSERT INTO school_programs (college_id, code, name, max_year_level, is_active) VALUES (?, ?, ?, ?, ?)', 'issii', [$college, $code, $name, $years, $active]);
        } elseif ($kind === 'term') {
            $year = self::text($input, 'academic_year', 9, true);
            if (!preg_match('/^(20\d{2})-(20\d{2})$/', $year, $parts) || (int)$parts[2] !== (int)$parts[1] + 1) throw new StudentAccountException('Academic year must be consecutive years, such as 2026-2027.');
            $semester = self::text($input, 'semester', 20, true);
            if (!in_array($semester, ['1st Semester', '2nd Semester', 'Summer'], true)) throw new StudentAccountException('Invalid semester.');
            $open = ($input['registration_open'] ?? '') === '1' ? 1 : 0;
            if ($id) $this->query('UPDATE school_terms SET academic_year = ?, semester = ?, registration_open = ? WHERE term_id = ?', 'ssii', [$year, $semester, $open, $id]);
            else $this->query('INSERT INTO school_terms (academic_year, semester, registration_open) VALUES (?, ?, ?)', 'ssi', [$year, $semester, $open]);
        } else throw new StudentAccountException('Unknown catalog type.');
    }
}
