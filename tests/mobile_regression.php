<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/config/env.php';
if (!in_array(env('DB_HOST', '127.0.0.1'), ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Mobile tests require a local database.');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$server = new mysqli(env('DB_HOST', '127.0.0.1'), env('DB_USER', 'root'), env('DB_PASS', ''));
$database = 'helpdeskcrmc_test_' . bin2hex(random_bytes(6));
$created = false;
$db = null;
try {
    $server->query('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
    $created = true;
    putenv('DB_NAME=' . $database);
    putenv('HELPDESK_TEST_DB=' . $database);
    require_once __DIR__ . '/../app/models/User.php';
    require_once __DIR__ . '/../app/models/Inquiry.php';
    require_once __DIR__ . '/../app/models/ChatSession.php';
    $db = getDbConnection();
    foreach (['database.sql', '20261002_fix_helpdesk_workflows.sql', '20261002_add_chat_sessions.sql',
        '20261002_allow_multiple_chat_sessions.sql', '20261002_add_notification_delivery.sql',
        '20261003_add_admin_workspace.sql', '20261003_add_student_profiles.sql', '20261005_student_self_registration.sql'] as $file) {
        $db->multi_query(file_get_contents(__DIR__ . '/../database/migrations/' . $file));
        do { $r = $db->store_result(); if ($r) $r->free(); } while ($db->more_results() && $db->next_result());
    }
    $fixtures = ['database' => $database];
    $users = new User();
    foreach (['student' => ['student', null], 'staff' => ['staff', 1], 'admin' => ['admin', null]] as $name => [$role, $office]) {
        $fixtures[$name] = $users->create([
            'role' => $role, 'office_id' => $office, 'student_number' => $role === 'student' ? 'MOBILE-2026-00123' : null,
            'first_name' => 'John Renz', 'last_name' => 'Mobile Test', 'email' => $name . '@example.test',
            'password_hash' => password_hash('mobile-test', PASSWORD_DEFAULT),
        ]);
    }
    require_once __DIR__ . '/student_fixtures.php';
    studentProfileFixture($db, $fixtures['student']);
    $db->query('UPDATE student_profiles SET mobile_number = "09171234567" WHERE user_id = ' . $fixtures['student']);
    $model = new Inquiry();
    foreach (['On Hold', 'Resolved'] as $status) {
        $id = $model->create(['student_id' => $fixtures['student'], 'office_id' => 1,
            'subject' => 'Enrollment and transcript requirements for the upcoming academic semester',
            'message' => str_repeat('Please review my enrollment records and supporting documents. ', 8),
            'ai_priority' => 'High', 'ai_priority_reason' => 'Registration deadline requires staff review.']);
        $model->addResponse($id, $fixtures['staff'], 'Please send your updated student information so we can assist you.');
        $model->updateStatus($id, $status);
        $fixtures[$status === 'On Hold' ? 'onHold' : 'resolved'] = $id;
    }
    (new ChatSession())->save($fixtures['student'], 'Registrar', [
        ['role' => 'user', 'message' => 'What are the transcript requirements?'],
        ['role' => 'model', 'message' => 'Please contact the Registrar for your transcript requirements.'],
    ], 'Transcript requirements', str_repeat('a', 32));
    $node = getenv('HELPDESK_NODE_PATH') ?: 'node';
    $process = proc_open([$node, __DIR__ . '/mobile_visual_regression.cjs', PHP_BINARY, json_encode($fixtures), $argv[1] ?? 'verify'],
        [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR], $pipes, dirname(__DIR__), null, ['bypass_shell' => true]);
    if (!is_resource($process)) throw new RuntimeException('Unable to start mobile browser checks.');
    fclose($pipes[0]);
    if (proc_close($process) !== 0) throw new RuntimeException('Mobile browser checks failed.');
} finally {
    if ($db instanceof mysqli) $db->close();
    if ($created && preg_match('/^helpdeskcrmc_test_[a-f0-9]{12}$/', $database)) $server->query('DROP DATABASE `' . $database . '`');
    $server->close();
}
