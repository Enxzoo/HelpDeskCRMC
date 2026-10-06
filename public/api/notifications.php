<?php
require_once __DIR__ . '/../../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../app/models/Notification.php';
require_once __DIR__ . '/../../app/services/NotificationDispatcher.php';
require_once __DIR__ . '/../../app/helpers/csrf.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
session_start();
$user = requireApiUser(['student', 'staff', 'admin']);
$userId = (int) $user['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'], true)) {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}
if ($method === 'POST' && !csrf_verify_header()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}
session_write_close();
try {
    $model = new Notification();
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input) || !is_string($input['action'] ?? null))
            throw new InvalidArgumentException('Invalid notification action.');
        switch ($input['action']) {
            case 'mark_read':
                $id = filter_var($input['notification_id'] ?? null, FILTER_VALIDATE_INT);
                if (!$id || $id < 1)
                    throw new InvalidArgumentException('Invalid notification ID.');
                $model->markRead($userId, $id);
                break;
            case 'mark_all_read':
                $model->markRead($userId);
                break;
            case 'email_preference':
                if (!is_bool($input['enabled'] ?? null))
                    throw new InvalidArgumentException('Invalid email preference.');
                $model->setEmailEnabled($userId, $input['enabled']);
                break;
            case 'subscribe':
                if (!is_array($input['subscription'] ?? null))
                    throw new InvalidArgumentException('Invalid push subscription.');
                $model->subscribe($userId, $input['subscription']);
                break;
            case 'unsubscribe':
                if (!is_string($input['endpoint'] ?? null) || strlen($input['endpoint']) > 2048)
                    throw new InvalidArgumentException('Invalid endpoint.');
                $model->unsubscribe($userId, $input['endpoint']);
                break;
            default:
                throw new InvalidArgumentException('Invalid notification action.');
        }
    }
    $feed = $model->feed($userId);
    echo json_encode([
        'success' => true,
        'user_id' => $userId,
        'role' => $user['role'],
        'email_enabled' => $model->emailEnabled($userId),
        'email_ready' => NotificationDispatcher::emailReady(),
        'push_ready' => NotificationDispatcher::pushReady(),
        'push_public_key' => env('VAPID_PUBLIC_KEY', ''),
    ] + $feed, JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $exception->getMessage()]);
} catch (Throwable $exception) {
    error_log('Notification request failed.');
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Notifications are temporarily unavailable.']);
}
