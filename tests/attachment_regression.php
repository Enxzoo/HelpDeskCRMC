<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/config/env.php';
if (!in_array(env('DB_HOST', '127.0.0.1'), ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Attachment tests require a local database.');
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$server = new mysqli(env('DB_HOST', '127.0.0.1'), env('DB_USER', 'root'), env('DB_PASS', ''));
$database = 'helpdeskcrmc_test_' . bin2hex(random_bytes(6));
$created = false;
$db = null;
$checks = 0;
function checkAttachment(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
try {
    $server->query('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
    $created = true;
    putenv('DB_NAME=' . $database);
    putenv('HELPDESK_TEST_DB=' . $database);
    require_once __DIR__ . '/../app/controllers/InquiryController.php';
    require_once __DIR__ . '/../app/models/User.php';
    $db = getDbConnection();
    foreach (['database.sql', '20261002_fix_helpdesk_workflows.sql', '20261002_add_notification_delivery.sql',
        '20261003_add_student_profiles.sql', '20261005_student_self_registration.sql'] as $file) {
        $db->multi_query(file_get_contents(__DIR__ . '/../database/migrations/' . $file));
        do { $result = $db->store_result(); if ($result) $result->free(); } while ($db->more_results() && $db->next_result());
    }
    $users = new User();
    $fixtures = ['database' => $database];
    foreach (['student' => ['student', null], 'otherStudent' => ['student', null], 'staff' => ['staff', 1],
        'otherStaff' => ['staff', 2], 'unassigned' => ['staff', null], 'admin' => ['admin', null]] as $name => [$role, $office]) {
        $fixtures[$name] = $users->create([
            'role' => $role, 'office_id' => $office, 'student_number' => $role === 'student' ? 'ATT-' . $name : null,
            'first_name' => 'Attachment', 'last_name' => $name, 'email' => $name . '@example.test',
            'password_hash' => password_hash('attachment-test', PASSWORD_DEFAULT),
        ]);
    }
    $model = new Inquiry();
    $insert = $db->prepare('INSERT INTO inquiry_attachments (uploaded_by, file_name, file_path, file_type, file_size)
        VALUES (?, "proof.pdf", "test-only-metadata", "application/pdf", 12)');
    $uploads = [];
    foreach (['student', 'student', 'otherStudent'] as $name) {
        $insert->bind_param('i', $fixtures[$name]);
        $insert->execute();
        $uploads[] = $db->insert_id;
    }
    $data = ['student_id' => $fixtures['student'], 'office_id' => 1, 'message' => 'Attached proof', 'subject' => 'Attachment check'];
    $before = (int)$db->query('SELECT COUNT(*) FROM inquiries')->fetch_row()[0];
    foreach ([[$uploads[0], $uploads[2]], [$uploads[0], $uploads[0]], [0], ['1'], [[1]], range(1, 6)] as $invalid) {
        try {
            $model->create($data + ['attachment_ids' => $invalid]);
            throw new RuntimeException('Invalid attachment IDs were accepted.');
        } catch (InvalidArgumentException $expected) {
            checkAttachment((int)$db->query('SELECT COUNT(*) FROM inquiries')->fetch_row()[0] === $before, 'Failed linking created an inquiry.');
        }
    }
    checkAttachment($db->query('SELECT inquiry_id FROM inquiry_attachments WHERE attachment_id = ' . $uploads[0])->fetch_row()[0] === null,
        'Failed multi-file submission partially linked an attachment.');
    $controller = new InquiryController(
        new ConcernUrgencyClassifier(fn() => '{"priority":"Normal","reason":"Routine support","confidence":0.9}', ''),
        new ConcernDuplicateDetector(fn() => '{"candidate_number":null,"confidence":0}', '')
    );
    $submitted = $controller->submit($fixtures['student'], 'Attached proof for staff', 1, 'Attachment check', null, [$uploads[0], $uploads[1]]);
    checkAttachment($submitted['success'], 'Controller did not submit the attachment concern.');
    $attachments = $model->getAttachments($submitted['inquiry_id']);
    checkAttachment(count($attachments) === 2, 'Attachments were not linked to the submitted concern.');
    checkAttachment(!isset($attachments[0]['file_path']) && !isset($attachments[0]['uploaded_by']), 'Attachment listing exposes storage or ownership metadata.');
    try {
        $model->create($data + ['attachment_ids' => [$uploads[0]]]);
        throw new RuntimeException('Linked attachment was reused.');
    } catch (InvalidArgumentException $expected) {
        checkAttachment(count($model->getAttachments($submitted['inquiry_id'])) === 2, 'Attachment relinking changed the original concern.');
    }
    $node = getenv('HELPDESK_NODE_PATH') ?: 'node';
    $process = proc_open([$node, __DIR__ . '/attachment_http_regression.cjs', PHP_BINARY, json_encode($fixtures)],
        [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR], $pipes, dirname(__DIR__), null, ['bypass_shell' => true]);
    if (!is_resource($process)) throw new RuntimeException('Unable to start attachment HTTP tests.');
    fclose($pipes[0]);
    checkAttachment(proc_close($process) === 0, 'Attachment HTTP regression failed.');
    echo 'Attachment transaction and ownership checks passed: ' . $checks . PHP_EOL;
} finally {
    if ($db instanceof mysqli) {
        $result = $db->query('SELECT file_path FROM inquiry_attachments');
        $root = realpath(__DIR__ . '/../storage/attachments');
        while ($row = $result->fetch_assoc()) {
            if (!preg_match('~^storage/attachments/[a-f0-9]{32}\.[a-z0-9]+$~D', $row['file_path'])) continue;
            $file = realpath(__DIR__ . '/../' . $row['file_path']);
            if ($root && $file && str_starts_with($file, $root . DIRECTORY_SEPARATOR) && is_file($file)) unlink($file);
        }
        $db->close();
    }
    if ($created && preg_match('/^helpdeskcrmc_test_[a-f0-9]{12}$/', $database)) $server->query('DROP DATABASE `' . $database . '`');
    $server->close();
}
