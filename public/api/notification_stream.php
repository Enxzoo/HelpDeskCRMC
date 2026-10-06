<?php
require_once __DIR__ . '/../../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../app/models/Notification.php';
session_start();
$user = requireApiUser(['student', 'staff', 'admin']);
$userId = (int) $user['user_id'];
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}
$cursor = filter_var($_SERVER['HTTP_LAST_EVENT_ID'] ?? $_GET['after'] ?? 0, FILTER_VALIDATE_INT);
if ($cursor === false || $cursor < 0) {
    http_response_code(400);
    exit;
}
session_write_close();
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store');
header('X-Accel-Buffering: no');
set_time_limit(35);
while (ob_get_level() > 0)
    ob_end_flush();
echo "retry: 3000\n\n";
flush();
$end = microtime(true) + 25;
$lastUnread = null;
try {
    $model = new Notification();
    do {
        $active = getDbConnection()->prepare('SELECT is_active FROM users WHERE user_id = ?');
        $active->bind_param('i', $userId);
        $active->execute();
        if (!(int) ($active->get_result()->fetch_assoc()['is_active'] ?? 0)) {
            echo "event: signed-out\ndata: {}\n\n";
            flush();
            break;
        }
        $feed = $model->feed($userId, $cursor);
        if ($feed['items'] !== [] || $lastUnread !== $feed['unread_count']) {
            foreach ($feed['items'] as $item)
                $cursor = max($cursor, $item['notification_id']);
            echo 'id: ' . $cursor . "\nevent: notifications\ndata: " . json_encode([
                'items' => $feed['items'],
                'unread_count' => $feed['unread_count'],
                'cursor' => $cursor,
            ], JSON_THROW_ON_ERROR) . "\n\n";
            $lastUnread = $feed['unread_count'];
        } else {
            echo ": heartbeat\n\n";
        }
        flush();
        if (connection_aborted())
            break;
        sleep(2);
    } while (microtime(true) < $end);
} catch (Throwable $exception) {
    error_log('Notification stream interrupted.');
    echo "event: reconnect\ndata: {}\n\n";
    flush();
}
