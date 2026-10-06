<?php
/**
 * ai_chat.php
 * Public API endpoint for Ben chatbot - Powered by Google Gemini AI
 * Generates intelligent, contextual responses (NOT predefined answers)
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../app/config/env.php';
require_once __DIR__ . '/../../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../app/controllers/GeminiAiController.php';
require_once __DIR__ . '/../../app/helpers/csrf.php';
require_once __DIR__ . '/../../app/models/AdminWorkspace.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Require authenticated session
session_start();
requireApiUser(['student']);

// Verify CSRF token from header
if (!csrf_verify_header()) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input) || !is_string($input['message'] ?? null) || trim($input['message']) === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit;
}

$message = trim($input['message']);
$conversationHistory = $input['history'] ?? [];
if (!is_array($conversationHistory)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid conversation history']);
    exit;
}
foreach ($conversationHistory as $entry) {
    if (
        !is_array($entry) || !is_string($entry['message'] ?? null)
        || !in_array($entry['role'] ?? null, ['user', 'student', 'model', 'assistant'], true)
    ) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid conversation history']);
        exit;
    }
}
session_write_close();

// Call Gemini AI
$controller = new GeminiAiController();
$knowledge = [];
try {
    $knowledge = (new AdminWorkspace())->publishedKnowledge($message);
} catch (Throwable $error) {
    error_log('Published knowledge unavailable: ' . $error->getMessage());
}

// Generate AI response
$result = $controller->generateResponse($message, [
    'history' => $conversationHistory,
    'knowledge' => $knowledge,
]);

// Return response
if ($result['success']) {
    echo json_encode([
        'success' => true,
        'matched' => true,
        'answer' => $result['answer'],
        'source' => $result['source'] ?? 'gemini-ai',
        'generated' => $result['generated'] ?? true,
    ]);
} else {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'matched' => false,
        'error' => $result['error'] ?? 'Ben’s AI service is temporarily unavailable. Please try again shortly or submit your concern to staff.',
    ]);
}
