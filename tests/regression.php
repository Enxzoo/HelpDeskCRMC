<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../app/config/env.php';
if (!in_array(env('DB_HOST', '127.0.0.1'), ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Regression tests require a local database.');
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$server = new mysqli(env('DB_HOST', '127.0.0.1'), env('DB_USER', 'root'), env('DB_PASS', ''));
$testDatabase = 'helpdeskcrmc_test_' . bin2hex(random_bytes(6));
$databaseCreated = false;
$db = null;
$checks = 0;
function checkRegression(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
}
try {
    $server->query('CREATE DATABASE `' . $testDatabase . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
    $databaseCreated = true;
    putenv('DB_NAME=' . $testDatabase);
    putenv('HELPDESK_TEST_DB=' . $testDatabase);
    require_once __DIR__ . '/../app/models/User.php';
    require_once __DIR__ . '/../app/models/Inquiry.php';
    require_once __DIR__ . '/../app/controllers/AuthController.php';
    require_once __DIR__ . '/../app/controllers/InquiryController.php';
    require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
    $db = getDbConnection();
    foreach (['database.sql', '20261002_fix_helpdesk_workflows.sql', '20261002_add_chat_sessions.sql', '20261002_allow_multiple_chat_sessions.sql', '20261002_add_notification_delivery.sql', '20261003_add_admin_workspace.sql', '20261003_add_student_profiles.sql'] as $file) {
        $db->multi_query(file_get_contents(__DIR__ . '/../database/migrations/' . $file));
        do {
            $result = $db->store_result();
            if ($result) $result->free();
        } while ($db->more_results() && $db->next_result());
    }
    $db->query("SET SESSION sql_mode = 'STRICT_ALL_TABLES'");
    require __DIR__ . '/self_registration_migration.php';
    require_once __DIR__ . '/student_fixtures.php';
    $users = new User();
    $password = 'regression-password';
    $fixtures = [];
    foreach ([
        'student' => ['student', null],
        'otherStudent' => ['student', null],
        'staff' => ['staff', 1],
        'otherStaff' => ['staff', 2],
        'unassignedStaff' => ['staff', null],
        'admin' => ['admin', null],
    ] as $name => [$role, $office]) {
        $fixtures[$name] = $users->create([
            'role' => $role, 'office_id' => $office,
            'student_number' => $role === 'student' ? 'TEST-' . $name : null,
            'first_name' => 'Test', 'last_name' => $name,
            'email' => $name . '@example.test',
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }
    foreach (['student', 'otherStudent'] as $name) studentProfileFixture($db, $fixtures[$name]);
    $academic = studentAcademicFixture($db);
    $student = $users->findById($fixtures['student']);
    checkRegression($student['first_name'] === 'Test', 'First name was not preserved.');
    checkRegression($student['student_number'] === 'TEST-student', 'Student number was not preserved.');
    checkRegression(password_verify($password, $student['password_hash']), 'Password hash is invalid.');
    checkRegression(count($users->findAll()) === 9, 'User listing failed.');
    checkRegression(!array_key_exists('password_hash', $users->findAll()[0]), 'User listing exposes password hashes.');
    checkRegression($users->getStats()['total'] === 9, 'User statistics failed.');
    $sessionDirectory = __DIR__ . '/.runtime';
    if (!is_dir($sessionDirectory)) mkdir($sessionDirectory, 0700, true);
    session_save_path($sessionDirectory);
    session_start();
    $auth = new AuthController();
    checkRegression($auth->login('student@example.test', $password)['success'], 'Active student cannot sign in.');
    checkRegression($users->findById($fixtures['student'])['last_login_at'] !== null, 'Last login was not recorded.');
    (new StudentProfile())->setAccess($fixtures['admin'], ['user_id' => $fixtures['student'], 'is_active' => '0', 'reason' => 'Fixture suspension']);
    checkRegression(authenticatedUser() === null, 'Disabled session remains authorized.');
    checkRegression(!$auth->login('student@example.test', $password)['success'], 'Disabled student can sign in.');
    (new StudentProfile())->setAccess($fixtures['admin'], ['user_id' => $fixtures['student'], 'is_active' => '1', 'reason' => 'Fixture reinstatement']);
    $registrationPassword = 'student-register-1';
    $registration = $auth->registerStudent([
        'first_name' => 'New',
        'last_name' => 'Student',
        'student_number' => 'reg-2026-001',
        'email' => 'New.Student@Example.Test',
        'password' => $registrationPassword,
        'confirm_password' => $registrationPassword,
    ] + $academic);
    checkRegression($registration['success'] && $registration['redirect'] === 'dashboard_student.php', 'Student registration did not open the dashboard immediately.');
    checkRegression($_SESSION['role'] === 'student' && $_SESSION['office_id'] === null, 'Registered student was not signed in.');
    $registeredStudent = $users->findByEmail('new.student@example.test');
    checkRegression($registeredStudent !== null, 'Registered student was not persisted.');
    checkRegression($registeredStudent['student_number'] === 'REG-2026-001', 'Student number was not normalized.');
    checkRegression(password_verify($registrationPassword, $registeredStudent['password_hash']), 'Registered student password hash is invalid.');
    checkRegression($registeredStudent['last_login_at'] !== null, 'Registered student last login was not recorded.');
    checkRegression(!$auth->registerStudent([
        'first_name' => 'New',
        'last_name' => 'Duplicate',
        'student_number' => 'REG-2026-002',
        'email' => 'new.student@example.test',
        'password' => $registrationPassword,
        'confirm_password' => $registrationPassword,
    ] + $academic)['success'], 'Duplicate student email was allowed.');
    checkRegression(!$auth->registerStudent([
        'first_name' => 'New',
        'last_name' => 'Duplicate',
        'student_number' => 'REG-2026-001',
        'email' => 'student.duplicate@example.test',
        'password' => $registrationPassword,
        'confirm_password' => $registrationPassword,
    ] + $academic)['success'], 'Duplicate student number was allowed.');
    $fixtures['academic'] = $academic;
    $fixtures['registeredStudent'] = (int)$registeredStudent['user_id'];
    require __DIR__ . '/student_accounts_cases.php';
    $inquiries = new Inquiry();
    foreach (['inquiry' => 1, 'otherInquiry' => 2] as $name => $office) {
        $fixtures[$name] = $inquiries->create([
            'student_id' => $fixtures['student'], 'office_id' => $office,
            'subject' => 'Regression concern', 'message' => 'Regression description',
            'ai_priority' => 'Normal',
        ]);
    }
    checkRegression($inquiries->updateStatus($fixtures['inquiry'], 'On Hold'), 'On Hold status cannot be saved.');
    checkRegression($inquiries->findByStudent($fixtures['student'])[1]['status_class'] === 'onhold'
        || $inquiries->findByStudent($fixtures['student'])[0]['status_class'] === 'onhold', 'Student status mapping failed.');
    checkRegression(count($inquiries->findByOffice(1, 'on_hold')) === 1, 'On Hold filtering failed.');
    $inquiries->addResponse($fixtures['inquiry'], $fixtures['student'], 'Student reply');
    checkRegression($inquiries->getReplies($fixtures['inquiry'])[0]['sender_role'] === 'student', 'Student reply authorship was lost.');
    $urgency = new ConcernUrgencyClassifier(fn() => json_encode([
        'priority' => 'High', 'reason' => 'Registration deadline is tomorrow.', 'confidence' => 0.94,
    ]), '');
    $detector = new ConcernDuplicateDetector(fn() => '{"candidate_number":1,"confidence":0.93}', '');
    $controller = new InquiryController($urgency, $detector);
    $root = $controller->submit($fixtures['otherStudent'], 'I need an official list of my college marks.', 1, 'Academic record');
    checkRegression($root['success'] && $root['urgency_source'] === 'ai' && $root['urgency_priority'] === 'High', 'Groq urgency contract failed during submission.');
    $storedRoot = $inquiries->findById($root['inquiry_id']);
    checkRegression((float)$storedRoot['ai_priority_confidence'] === 0.94, 'Groq confidence was not persisted.');
    $warning = $controller->submit($fixtures['otherStudent'], 'Please provide a certified transcript showing completed subjects and grades.', 1, 'Transcript request');
    checkRegression(!$warning['success'] && $warning['duplicate_warning'] && $warning['duplicate_root_id'] === $root['inquiry_id'], 'Groq duplicate result did not trigger confirmation.');
    for ($attempt = 0; $attempt < 2; $attempt++) {
        $confirmed = $controller->submit($fixtures['otherStudent'], 'Please provide a certified transcript showing completed subjects and grades.', 1, 'Transcript request', $root['inquiry_id']);
        checkRegression($confirmed['success'] && $confirmed['duplicate_of_inquiry_id'] === $root['inquiry_id'], 'Confirmed duplicate could not be submitted.');
    }
    $limited = $controller->submit($fixtures['otherStudent'], 'Please provide a certified transcript showing completed subjects and grades.', 1, 'Transcript request', $root['inquiry_id']);
    checkRegression(!$limited['success'] && $limited['duplicate_limit_reached'], 'Groq migration bypassed duplicate submission limit.');
    $httpCommand = ['node', __DIR__ . '/http_regression.cjs', PHP_BINARY, json_encode($fixtures)];
    $process = proc_open($httpCommand, [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR], $pipes, dirname(__DIR__));
    if (!is_resource($process)) throw new RuntimeException('Unable to start HTTP regression tests.');
    fclose($pipes[0]);
    checkRegression(proc_close($process) === 0, 'HTTP regression tests failed.');
    echo 'PHP regression checks passed: ' . $checks . PHP_EOL;
} finally {
    if ($db instanceof mysqli) {
        $result = $db->query('SELECT file_path FROM inquiry_attachments');
        while ($row = $result->fetch_assoc()) {
            if (preg_match('/^uploads\/[a-f0-9]{32}\.[a-z0-9]+$/', $row['file_path'])) {
                $testFile = dirname(__DIR__) . '/' . $row['file_path'];
                if (is_file($testFile)) unlink($testFile);
            }
        }
        $db->close();
    }
    if ($databaseCreated && preg_match('/^helpdeskcrmc_test_[a-f0-9]{12}$/', $testDatabase)) {
        $server->query('DROP DATABASE `' . $testDatabase . '`');
    }
    $server->close();
}
