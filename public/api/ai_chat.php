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

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Require authenticated session
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Verify CSRF token from header
if (!csrf_verify_header()) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['message']) || empty(trim($input['message']))) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit;
}

$message = trim($input['message']);
$conversationHistory = $input['history'] ?? [];

// Log the request
error_log("Gemini AI Chat Request from user {$_SESSION['user_id']}: $message");

// Call Gemini AI
$controller = new GeminiAiController();

// Check if Gemini is configured
if (!$controller->isConfigured()) {
    error_log("Gemini API not configured - falling back to keyword matching");
    echo json_encode([
        'success' => false,
        'fallback' => true,
        'error' => 'AI service not configured'
    ]);
    exit;
}

// Generate AI response
$result = $controller->generateResponse($message, [
    'history' => $conversationHistory
]);

// Log the actual result for debugging
error_log("Ben AI Result: " . json_encode($result));

// Return response
if ($result['success']) {
    echo json_encode([
        'success' => true,
        'matched' => true,
        'answer' => $result['answer'],
        'source' => 'gemini-ai',
        'generated' => true
    ]);
} else {
    // If AI fails, return fallback flag so frontend can use keyword matching
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'matched' => false,
        'fallback' => $result['fallback'] ?? false,
        'error' => $result['error'] ?? 'Unknown error'
    ]);
}
