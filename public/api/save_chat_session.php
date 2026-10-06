<?php
/**
 * save_chat_session.php
 * Save chat conversation history to database
 */

header('Content-Type: application/json');
header('Cache-Control: no-store');
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../app/helpers/csrf.php';
require_once __DIR__ . '/../../app/models/ChatSession.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

session_start();
requireApiUser(['student']);

if (!csrf_verify_header()) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (
    !is_array($input) || !is_string($input['category'] ?? null) || trim($input['category']) === ''
    || mb_strlen($input['category']) > 100
) {
    http_response_code(400);
    echo json_encode(['error' => 'Category is required']);
    exit;
}

if (!isset($input['conversationHistory']) || !is_array($input['conversationHistory'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Conversation history is required']);
    exit;
}

$studentId = (int) $_SESSION['user_id'];
$category = trim($input['category']);
$conversationHistory = $input['conversationHistory'];
$sessionKey = $input['session_key'] ?? null;
if ($sessionKey !== null && !ChatSession::validKey($sessionKey)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid conversation ID']);
    exit;
}
$lastMessage = $input['lastMessage'] ?? null;
foreach ($conversationHistory as $entry) {
    if (
        !is_array($entry) || !is_string($entry['message'] ?? null)
        || !in_array($entry['role'] ?? null, ['user', 'model'], true)
    ) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid conversation history']);
        exit;
    }
}
if ($lastMessage !== null && !is_string($lastMessage)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid last message']);
    exit;
}
$lastMessage = $lastMessage === null ? null : mb_substr($lastMessage, 0, 500);
session_write_close();

try {
    $saved = (new ChatSession())->save($studentId, $category, $conversationHistory, $lastMessage, $sessionKey);
    echo json_encode(['success' => true, 'session_id' => $saved['session_id'], 'session_key' => $saved['session_key'], 'session' => $saved]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save chat session']);
}
