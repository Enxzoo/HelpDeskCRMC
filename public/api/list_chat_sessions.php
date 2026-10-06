<?php
require_once __DIR__ . '/../../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../app/models/ChatSession.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
session_start();
$user = requireApiUser(['student']);
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}
session_write_close();
try {
    echo json_encode(['success' => true, 'sessions' => (new ChatSession())->recent((int) $user['user_id'])], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Chat history is temporarily unavailable.']);
}
