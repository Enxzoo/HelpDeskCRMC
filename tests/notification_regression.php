<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/config/env.php';
if (!in_array(env('DB_HOST', '127.0.0.1'), ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Notification tests require a local database.');
}
$opensslConfig = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';
if (PHP_OS_FAMILY === 'Windows' && !getenv('OPENSSL_CONF') && is_file($opensslConfig)) {
    $child = proc_open([PHP_BINARY, ...$argv], [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes,
        null, ['OPENSSL_CONF' => $opensslConfig] + getenv(), ['bypass_shell' => true]);
    if (!is_resource($child)) exit(1);
    exit(proc_close($child));
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$server = new mysqli(env('DB_HOST', '127.0.0.1'), env('DB_USER', 'root'), env('DB_PASS', ''));
$testDatabase = 'helpdeskcrmc_test_' . bin2hex(random_bytes(6));
$created = false;
$db = null;
$checks = 0;
function checkNotification(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function notificationCount(string $where = '1'): int
{
    return (int)getDbConnection()->query('SELECT COUNT(*) FROM notifications WHERE ' . $where)->fetch_row()[0];
}
try {
    $server->query('CREATE DATABASE `' . $testDatabase . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
    $created = true;
    putenv('DB_NAME=' . $testDatabase);
    require_once __DIR__ . '/../app/models/User.php';
    require_once __DIR__ . '/../app/models/Inquiry.php';
    require_once __DIR__ . '/../app/services/NotificationDispatcher.php';
    $db = getDbConnection();
    foreach (['database.sql', '20261002_fix_helpdesk_workflows.sql', '20261002_add_notification_delivery.sql', '20261003_add_student_profiles.sql', '20261005_student_self_registration.sql'] as $file) {
        $db->multi_query(file_get_contents(__DIR__ . '/../database/migrations/' . $file));
        do {
            $result = $db->store_result();
            if ($result) $result->free();
        } while ($db->more_results() && $db->next_result());
    }
    $db->query('UPDATE users SET is_active = 0');
    $users = new User();
    $ids = [];
    foreach (['student' => ['student', null], 'otherStudent' => ['student', null],
        'staff' => ['staff', 1], 'otherStaff' => ['staff', 2], 'inactiveStaff' => ['staff', 1],
        'admin' => ['admin', null]] as $name => [$role, $office]) {
        $ids[$name] = $users->create([
            'role' => $role, 'office_id' => $office, 'student_number' => $role === 'student' ? 'NOTIFY-' . $name : null,
            'first_name' => 'Notify', 'last_name' => $name, 'email' => $name . '@example.test',
            'password_hash' => password_hash('notification-test', PASSWORD_DEFAULT),
        ]);
    }
    require_once __DIR__ . '/student_fixtures.php';
    foreach (['student', 'otherStudent'] as $name) studentProfileFixture($db, $ids[$name]);
    $users->toggleStatus($ids['inactiveStaff']);
    $inquiries = new Inquiry();
    $model = new Notification();
    $inquiryId = $inquiries->create(['student_id' => $ids['student'], 'office_id' => 1,
        'message' => 'PRIVATE DESCRIPTION', 'subject' => 'PRIVATE SUBJECT', 'ai_priority' => 'High']);
    $inquiry = $inquiries->findById($inquiryId);
    checkNotification(notificationCount() === 3, 'A new concern must notify exactly the student, office staff, and admin.');
    foreach (['student', 'staff', 'admin'] as $name) {
        checkNotification($model->feed($ids[$name])['unread_count'] === 1, 'Expected recipient did not receive an alert: ' . $name);
    }
    foreach (['otherStudent', 'otherStaff', 'inactiveStaff'] as $name) {
        checkNotification($model->feed($ids[$name])['items'] === [], 'An unauthorized recipient received an alert: ' . $name);
    }
    (new NotificationService())->created($inquiry);
    checkNotification(notificationCount() === 3, 'Retrying the same event duplicated notifications.');
    checkNotification((int)$db->query('SELECT COUNT(*) FROM notification_deliveries')->fetch_row()[0] === 3, 'Email deliveries were not queued exactly once.');
    $inquiries->updateStatus($inquiryId, 'Pending');
    checkNotification(notificationCount() === 3, 'An unchanged status generated an alert.');
    $responseId = $inquiries->addResponse($inquiryId, $ids['staff'], 'PRIVATE REPLY', 'On Hold');
    checkNotification(notificationCount() === 4 && $inquiries->findById($inquiryId)['status'] === 'On Hold', 'Reply and status change were not combined.');
    $studentAlert = $model->feed($ids['student'])['items'][0];
    checkNotification(str_contains($studentAlert['message'], 'On Hold') && !str_contains(json_encode($studentAlert), 'PRIVATE'), 'Alerts leaked private contents or missed the status.');
    (new NotificationService())->response($inquiries->findById($inquiryId), $responseId, $users->findById($ids['staff']));
    checkNotification(notificationCount() === 4, 'A repeated reply event generated duplicate alerts.');
    $inquiries->addResponse($inquiryId, $ids['student'], 'PRIVATE FOLLOW-UP');
    checkNotification(notificationCount() === 6 && $model->feed($ids['staff'])['unread_count'] === 2, 'Student reply did not notify the office and admin.');
    foreach (['otherStudent', 'otherStaff', 'inactiveStaff'] as $name) {
        try { $inquiries->addResponse($inquiryId, $ids[$name], 'Denied'); throw new RuntimeException('Unauthorized reply accepted.'); }
        catch (ResponseNotAllowedException $exception) { $checks++; }
    }
    checkNotification(notificationCount() === 6 && count($inquiries->getReplies($inquiryId)) === 2, 'Rejected replies left data behind.');
    checkNotification(!$model->markRead($ids['otherStudent'], $studentAlert['notification_id']), 'Another account marked the student notification as read.');
    checkNotification($model->markRead($ids['student'], $studentAlert['notification_id']), 'The owner could not mark their alert as read.');
    checkNotification($model->feed($ids['student'])['unread_count'] === 1, 'Unread count is incorrect after reading.');
    $model->markRead($ids['student']);
    checkNotification($model->feed($ids['student'])['unread_count'] === 0, 'Mark all read failed.');
    checkNotification($model->feed($ids['student'], $studentAlert['notification_id'])['items'] === [], 'The event cursor replayed older notifications.');
    $db->query("UPDATE users SET office_id = 2 WHERE user_id = {$ids['staff']}");
    checkNotification($model->feed($ids['staff'])['items'] === [], 'An office change exposed old notifications.');
    $db->query("UPDATE users SET office_id = 1 WHERE user_id = {$ids['staff']}");

    // A queue insertion failure must roll back both the reply and its status change.
    $db->query("CREATE TRIGGER fail_notification BEFORE INSERT ON notifications FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Injected notification failure'");
    try { $inquiries->addResponse($inquiryId, $ids['staff'], 'Rollback reply', 'Resolved'); throw new RuntimeException('Injected failure was ignored.'); }
    catch (mysqli_sql_exception $exception) { $checks++; }
    $db->query('DROP TRIGGER fail_notification');
    checkNotification($inquiries->findById($inquiryId)['status'] === 'On Hold' && count($inquiries->getReplies($inquiryId)) === 2, 'Notification failure did not roll back the operation.');

    $vapid = \Minishlink\WebPush\VAPID::createVapidKeys();
    $subscriber = \Minishlink\WebPush\VAPID::createVapidKeys();
    $subscription = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/test-only',
        'keys' => ['p256dh' => $subscriber['publicKey'], 'auth' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=')]];
    checkNotification(Notification::validSubscription($subscription), 'Valid browser subscription rejected.');
    foreach (['http://fcm.googleapis.com/test', 'https://127.0.0.1/test', 'https://fcm.googleapis.com.evil.test/test',
        'https://user@fcm.googleapis.com/test', 'https://fcm.googleapis.com:8443/test', 'https://fcm.googleapis.com/test#fragment'] as $endpoint) {
        checkNotification(!Notification::validSubscription(array_replace($subscription, ['endpoint' => $endpoint])), 'Unsafe push endpoint accepted: ' . $endpoint);
    }
    checkNotification(!Notification::validSubscription(['endpoint' => $subscription['endpoint'], 'keys' => 'invalid']), 'Malformed keys accepted.');
    checkNotification(!Notification::validSubscription(['endpoint' => $subscription['endpoint'], 'keys' => ['p256dh' => 'bad', 'auth' => 'bad']]), 'Invalid key lengths accepted.');
    $model->subscribe($ids['student'], $subscription);
    $model->subscribe($ids['student'], $subscription);
    checkNotification((int)$db->query('SELECT COUNT(*) FROM push_subscriptions')->fetch_row()[0] === 1, 'Push subscription was duplicated.');
    $db->query('DELETE FROM notification_deliveries');
    $model->record($ids['student'], $inquiryId, 'Test alert', 'No private contents', 'test:delivery');
    checkNotification((int)$db->query('SELECT COUNT(*) FROM notification_deliveries')->fetch_row()[0] === 2, 'Email and push were not queued together.');
    $sent = [];
    $mock = function (array $row) use (&$sent): bool { $sent[] = $row; return true; };
    $dispatcher = new NotificationDispatcher($mock, $mock);
    checkNotification($dispatcher->runBatch()['sent'] === 2 && count($sent) === 2, 'Mock delivery did not process both channels.');
    checkNotification($dispatcher->runBatch()['sent'] === 0 && count($sent) === 2, 'Delivered notifications were sent again.');

    $model->record($ids['student'], $inquiryId, 'Disabled email', 'Generic', 'test:disabled');
    $model->setEmailEnabled($ids['student'], false);
    $batch = $dispatcher->runBatch();
    checkNotification($batch['skipped'] === 1 && $batch['sent'] === 1, 'The worker ignored an updated email preference.');
    $before = (int)$db->query('SELECT COUNT(*) FROM notification_deliveries')->fetch_row()[0];
    $model->record($ids['student'], $inquiryId, 'No email queue', 'Generic', 'test:no-email');
    checkNotification((int)$db->query('SELECT COUNT(*) FROM notification_deliveries')->fetch_row()[0] === $before + 1, 'Disabled email preference still queued email.');
    $model->subscribe($ids['otherStudent'], $subscription);
    checkNotification($dispatcher->runBatch()['skipped'] === 1, 'A shared browser received the previous owner notification.');
    $model->unsubscribe($ids['student'], $subscription['endpoint']);
    checkNotification((int)$db->query('SELECT COUNT(*) FROM push_subscriptions')->fetch_row()[0] === 1, 'Another user deleted the browser subscription.');
    $model->subscribe($ids['student'], $subscription);
    $model->record($ids['student'], $inquiryId, 'Expired push', 'Generic', 'test:expired');
    $expired = new NotificationDispatcher(null, fn() => 'expired');
    checkNotification($expired->runBatch()['skipped'] === 1 && (int)$db->query('SELECT COUNT(*) FROM push_subscriptions')->fetch_row()[0] === 0, 'Expired subscription was not removed.');

    $db->query('DELETE FROM notification_deliveries');
    $model->setEmailEnabled($ids['student'], true);
    $retryId = $model->record($ids['student'], $inquiryId, 'Retry email', 'Generic', 'test:retry');
    $fail = new NotificationDispatcher(fn() => false);
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $db->query('UPDATE notification_deliveries SET available_at = NOW()');
        checkNotification($fail->runBatch()['failed'] === 1, 'Failed transport was not recorded.');
        $row = $db->query('SELECT * FROM notification_deliveries')->fetch_assoc();
        checkNotification((int)$row['attempts'] === $attempt && $row['status'] === ($attempt === 5 ? 'failed' : 'pending'), 'Retry count/status is incorrect.');
        checkNotification(strtotime($row['available_at']) > time() && !str_contains($row['last_error'], 'password'), 'Retry backoff or sanitized error is missing.');
        checkNotification($fail->runBatch()['failed'] === 0, 'The worker retried before its scheduled time.');
    }
    $db->query('DELETE FROM notification_deliveries');
    $model->record($ids['student'], $inquiryId, 'Unconfigured email', 'Generic', 'test:unconfigured');
    putenv('SMTP_USERNAME=');
    putenv('SMTP_PASSWORD=');
    checkNotification((new NotificationDispatcher())->runBatch()['sent'] === 0, 'Unconfigured email was sent.');
    $row = $db->query('SELECT status, attempts FROM notification_deliveries')->fetch_assoc();
    checkNotification($row['status'] === 'pending' && (int)$row['attempts'] === 0, 'Unconfigured email consumed a retry.');
    $db->query('UPDATE notification_deliveries SET status = "processing", locked_at = NOW()');
    checkNotification($dispatcher->runBatch()['sent'] === 0, 'An active delivery lease was processed twice.');
    $db->query('UPDATE notification_deliveries SET locked_at = DATE_SUB(NOW(), INTERVAL 6 MINUTE)');
    checkNotification($dispatcher->runBatch()['sent'] === 1, 'An abandoned delivery lease was not recovered.');
    $db->query('DELETE FROM notification_deliveries');
    $model->record($ids['staff'], $inquiryId, 'Office transfer', 'Generic', 'test:office-transfer');
    $db->query("UPDATE users SET office_id = 2 WHERE user_id = {$ids['staff']}");
    checkNotification($dispatcher->runBatch()['skipped'] === 1, 'The worker sent an alert after an office transfer.');
    $model->record($ids['student'], $inquiryId, 'Inactive account', 'Generic', 'test:inactive');
    (new StudentProfile())->setAccess($ids['admin'], ['user_id' => $ids['student'], 'is_active' => '0', 'reason' => 'Notification suspension fixture.']);
    checkNotification($dispatcher->runBatch()['skipped'] === 1 && $model->feed($ids['student'])['items'] === [], 'An inactive account received alerts.');

    putenv('SMTP_HOST=smtp.gmail.com');
    putenv('SMTP_USERNAME=sender@example.test');
    putenv('SMTP_PASSWORD=fake-test-password');
    putenv('SMTP_FROM_EMAIL=sender@example.test');
    putenv('APP_URL=https://helpdesk.example.test/public');
    checkNotification(NotificationDispatcher::emailReady(), 'Configured SMTP readiness is incorrect.');
    $method = new ReflectionMethod(NotificationDispatcher::class, 'buildEmail');
    $mail = $method->invoke($dispatcher, ['notification_id' => 1, 'user_id' => $ids['student'], 'email' => 'recipient@example.test',
        'title' => '<Test alert>', 'message' => 'Generic <text>', 'url' => NotificationDispatcher::link('student', $inquiryId)]);
    checkNotification($mail->SMTPSecure === 'tls' && $mail->SMTPAuth && $mail->Host === 'smtp.gmail.com', 'Email transport was not configured securely.');
    checkNotification(str_contains($mail->Body, '&lt;Test alert&gt;') && !str_contains($mail->Body, 'PRIVATE'), 'Email body is unsafe.');
    checkNotification($mail->preSend() && str_contains($mail->getSentMIMEMessage(), 'recipient@example.test'), 'PHPMailer could not compose the actual notification email.');

    // Exercise real encryption and VAPID signing without contacting a push service.
    $history = [];
    $handler = \GuzzleHttp\HandlerStack::create(new \GuzzleHttp\Handler\MockHandler([new \GuzzleHttp\Psr7\Response(201)]));
    $handler->push(\GuzzleHttp\Middleware::history($history));
    $push = new \Minishlink\WebPush\WebPush(['VAPID' => ['subject' => 'https://helpdesk.example.test'] + $vapid],
        ['TTL' => 3600], 10, ['handler' => $handler, 'allow_redirects' => false]);
    $report = $push->sendOneNotification(\Minishlink\WebPush\Subscription::create($subscription + ['contentEncoding' => 'aes128gcm']), '{"title":"Test alert"}');
    checkNotification($report->isSuccess() && count($history) === 1, 'Web Push encryption/signing failed.');
    checkNotification(str_starts_with($history[0]['request']->getHeaderLine('Authorization'), 'vapid ')
        && $history[0]['request']->getHeaderLine('Content-Encoding') === 'aes128gcm', 'Encrypted push headers are missing.');
    echo 'Notification PHP regression checks passed: ' . $checks . PHP_EOL;
} finally {
    if ($db instanceof mysqli) $db->close();
    if ($created && preg_match('/^helpdeskcrmc_test_[a-f0-9]{12}$/', $testDatabase)) $server->query('DROP DATABASE `' . $testDatabase . '`');
    $server->close();
}
