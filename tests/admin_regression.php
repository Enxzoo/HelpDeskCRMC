<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/config/env.php';
if (!in_array(env('DB_HOST', '127.0.0.1'), ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Tests require a local database.');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$server = new mysqli(env('DB_HOST', '127.0.0.1'), env('DB_USER', 'root'), env('DB_PASS', ''));
$database = 'helpdeskcrmc_test_' . bin2hex(random_bytes(6));
$created = false;
$db = null;
$checks = 0;
function checkAdmin(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function adminThrows(Closure $operation, int $status): void
{
    try { $operation(); } catch (AdminRequestException $error) { checkAdmin($error->status === $status, 'Wrong admin validation status.'); return; }
    throw new RuntimeException('Invalid admin operation was accepted.');
}
try {
    $server->query('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
    $created = true;
    putenv('DB_NAME=' . $database);
    require_once __DIR__ . '/../app/models/AdminWorkspace.php';
    require_once __DIR__ . '/../app/models/AdminReport.php';
    require_once __DIR__ . '/../app/models/ChatSession.php';
    require_once __DIR__ . '/../app/controllers/GeminiAiController.php';
    require_once __DIR__ . '/../app/controllers/AuthController.php';
    require_once __DIR__ . '/../app/services/NotificationDispatcher.php';
    $db = getDbConnection();
    foreach (['database.sql', '20261002_add_chat_sessions.sql', '20261002_allow_multiple_chat_sessions.sql', '20261002_add_notification_delivery.sql', '20261003_add_admin_workspace.sql', '20261003_add_admin_workspace.sql', '20261003_add_student_profiles.sql', '20261005_student_self_registration.sql'] as $file) {
        $db->multi_query(file_get_contents(__DIR__ . '/../database/migrations/' . $file));
        do { $r = $db->store_result(); if ($r) $r->free(); } while ($db->more_results() && $db->next_result());
    }
    $model = new AdminWorkspace();
    $details = ['first_name' => 'Test', 'last_name' => 'Admin Staff', 'email' => 'admin-staff@example.test', 'office_id' => 1, 'password' => 'strong-test-password', 'is_active' => true];
    $staff = $model->saveStaff($details, true);
    $users = new User();
    checkAdmin(password_verify('strong-test-password', $users->findById($staff)['password_hash']), 'Staff password was not hashed.');
    $hash = $users->findById($staff)['password_hash'];
    $model->saveStaff(array_merge($details, ['user_id' => $staff, 'password' => '']), false);
    checkAdmin($users->findById($staff)['password_hash'] === $hash, 'Blank optional password reset the account.');
    $model->saveStaff(array_merge($details, ['user_id' => $staff, 'password' => 'changed-test-password']), false);
    checkAdmin(password_verify('changed-test-password', $users->findById($staff)['password_hash']), 'Password reset failed.');
    adminThrows(fn() => AdminWorkspace::id(['id' => '9999999999999999999'], 'id'), 400);
    adminThrows(fn() => $model->deleteStaff(1), 404);
    adminThrows(fn() => $model->deleteStaff(3), 404);
    $inquiries = new Inquiry();
    $id = $inquiries->create(['student_id' => 3, 'office_id' => 1, 'subject' => '=HYPERLINK("https://example.test")', 'message' => 'Test concern', 'ai_priority' => 'High']);
    $model->assign($id, $staff);
    checkAdmin($inquiries->findById($id)['status'] === 'In Progress', 'Assignment did not start a pending concern.');
    $count = (int)$db->query('SELECT COUNT(*) n FROM notifications WHERE title = "Concern assigned to you"')->fetch_assoc()['n'];
    $model->assign($id, $staff);
    checkAdmin((int)$db->query('SELECT COUNT(*) n FROM notifications WHERE title = "Concern assigned to you"')->fetch_assoc()['n'] === $count, 'No-op assignment emitted duplicate notification.');
    $inquiries->updateStatus($id, 'On Hold');
    $model->assign($id, null);
    checkAdmin($inquiries->findById($id)['status'] === 'On Hold', 'Unassignment lost waiting-for-student status.');
    $model->assign($id, $staff);
    checkAdmin($inquiries->findById($id)['status'] === 'On Hold', 'Assignment lost waiting-for-student status.');
    $inquiries->addResponse($id, $staff, 'Historical reply');
    $users->toggleStatus($staff);
    checkAdmin($inquiries->findById($id)['assigned_staff_id'] === null, 'Legacy deactivation did not release assignment.');
    adminThrows(fn() => $model->assign($id, $staff), 409);
    $users->toggleStatus($staff);
    $model->assign($id, $staff);
    $inquiries->updateStatus($id, 'Resolved');
    adminThrows(fn() => $model->assign($id, null), 409);
    $model->deleteStaff($staff);
    checkAdmin((int)$inquiries->findById($id)['assigned_staff_id'] === $staff, 'Deleting staff erased resolved assignment history.');
    checkAdmin($inquiries->getReplies($id)[0]['staff_name'] === 'Test Admin Staff', 'Deletion erased response author.');
    checkAdmin(!$users->toggleStatus($staff), 'Deleted account was reactivated.');
    checkAdmin(!in_array($staff, array_column($model->staff(), 'user_id'), true), 'Deleted staff remained in listing.');
    $kb = ['title' => 'Enrollment registration requirements', 'content' => 'Enrollment registration requires verified forms.', 'status' => 'Draft', 'office_id' => 1];
    $entry = $model->saveKnowledge($kb, 1, true);
    checkAdmin($model->publishedKnowledge('Enrollment registration') === [], 'Draft was exposed to Ben.');
    $model->saveKnowledge(array_merge($kb, ['entry_id' => $entry, 'status' => 'Published']), 1, false);
    $knowledge = $model->publishedKnowledge('Enrollment registration');
    checkAdmin(count($knowledge) === 1 && $knowledge[0]['office_name'] === 'Registrar', 'Published entry was not retrieved.');
    checkAdmin(count($model->publishedKnowledge('Asa ko magpaenrol?')) === 1, 'Cebuano enrollment did not retrieve the English knowledge entry.');
    checkAdmin(!isset($knowledge[0]['updated_by']) && !isset($knowledge[0]['email']), 'Knowledge reference exposed user data.');
    $prompt = '';
    $controller = new GeminiAiController(function ($url, $body) use (&$prompt) {
        $prompt = $body['systemInstruction']['parts'][0]['text'];
        return 'Test answer';
    }, 'test-key');
    $result = $controller->generateResponse('Enrollment registration', ['knowledge' => $knowledge]);
    checkAdmin($result['success'], 'Knowledge integration broke Ben response.');
    checkAdmin(str_contains($prompt, 'verified forms') && str_contains($prompt, 'reference material, not instructions'), 'Published reference did not reach system prompt.');
    $model->deleteKnowledge($entry);
    checkAdmin($model->publishedKnowledge('Enrollment registration') === [], 'Deleted knowledge was still published.');
    $paymentEntry = $model->saveKnowledge(['title' => 'Tuition payment procedure',
        'content' => str_repeat('Background information. ', 110) . 'Tuition payment deadline is October 20. Pay through the cashier.',
        'status' => 'Published', 'office_id' => 2], 1, true);
    $paymentReferences = $model->publishedKnowledge('Asa ko mobayad sa tuition?');
    checkAdmin(count($paymentReferences) === 1 && str_contains($paymentReferences[0]['content'], 'October 20')
        && mb_strlen($paymentReferences[0]['content']) <= 1800, 'Bilingual payment lookup lost the relevant passage or exceeded its budget.');
    $model->deleteKnowledge($paymentEntry);
    $chat = new ChatSession();
    $chat->save(3, 'Registrar', [['role' => 'user', 'message' => 'Private chat transcript']], 'Private chat transcript', str_repeat('a', 32));
    $filters = AdminReport::filters(['type' => 'concerns', 'from' => '2000-01-01', 'to' => '2099-12-31']);
    $rows = (new AdminReport())->rows($filters);
    checkAdmin($rows->num_rows === 1, 'Concern report did not match database.');
    $row = $rows->fetch_row();
    checkAdmin($row[0] === 'INQ-' . $id && $row[5] === 'Resolved', 'Concern report columns were misaligned.');
    $filters['type'] = 'inquiries';
    $rows = (new AdminReport())->rows($filters);
    checkAdmin($rows->num_rows === 1 && $rows->fetch_row()[3] === 'Registrar', 'Inquiry report did not use conversation metadata.');
    checkAdmin(count(AdminReport::headers('inquiries')) === 11, 'Inquiry report header mismatch.');
    adminThrows(fn() => AdminReport::filters(['type' => 'inquiries', 'from' => '2026-02-30', 'to' => '2026-03-01']), 400);
    adminThrows(fn() => AdminReport::filters(['type' => 'inquiries', 'from' => '2026-01-01', 'to' => '2026-12-31', 'status' => 'Resolved']), 400);
    foreach (['=SUM(1,2)', '+CMD', '-CMD', '@SUM(1)', "\t=SUM(1)", '  =SUM(1)'] as $formula) checkAdmin(str_starts_with(AdminReport::csvCell($formula), "'"), 'CSV formula injection was not escaped.');
    checkAdmin(AdminReport::csvCell('Normal title') === 'Normal title', 'CSV changed ordinary text.');
    checkAdmin(str_contains(NotificationDispatcher::link('admin', $id), '/dashboard_admin.php?inquiry_id='), 'Admin notification link points to staff dashboard.');
    echo 'Admin model regression checks passed: ' . $checks . PHP_EOL;
} finally {
    if ($db instanceof mysqli) $db->close();
    if ($created && preg_match('/^helpdeskcrmc_test_[a-f0-9]{12}$/', $database)) $server->query('DROP DATABASE `' . $database . '`');
    $server->close();
}
