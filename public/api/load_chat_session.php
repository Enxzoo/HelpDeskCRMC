<?php
/**
 * load_chat_session.php
 * Load chat conversation history from database
 */

header('Content-Type: application/json');
header('Cache-Control: no-store');
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../app/models/ChatSession.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

session_start();
requireApiUser(['student']);

$category = $_GET['category'] ?? null;
$sessionKey = $_GET['session_key'] ?? null;

if ($sessionKey !== null && !ChatSession::validKey($sessionKey)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid conversation ID']);
    exit;
}
if ($sessionKey === null && (!is_string($category) || trim($category) === '' || mb_strlen($category) > 100)) {
    http_response_code(400);
    echo json_encode(['error' => 'Category is required']);
    exit;
}

$studentId = (int) $_SESSION['user_id'];
session_write_close();

try {
    $model = new ChatSession();
    $row = $sessionKey !== null ? $model->find($studentId, $sessionKey) : $model->latestForCategory($studentId, trim($category));
    if ($row) {
        $conversationHistory = json_decode($row['session_data'], true, 512, JSON_THROW_ON_ERROR);

        echo json_encode([
            'success' => true,
            'session_id' => (int) $row['session_id'],
            'session_key' => $row['session_key'],
            'category' => $row['category'],
            'conversationHistory' => $conversationHistory,
            'lastMessage' => $row['last_message'],
            'updated_at' => $row['updated_at']
        ]);
    } else {
        if ($sessionKey !== null) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Conversation not found']);
            exit;
        }
        echo json_encode([
            'success' => true,
            'conversationHistory' => []
        ]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load chat session']);
}
