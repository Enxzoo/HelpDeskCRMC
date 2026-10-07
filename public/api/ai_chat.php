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
$rawInput = file_get_contents('php://input', false, null, 0, 131073);
if (strlen($rawInput) > 131072) {
    http_response_code(413);
    echo json_encode(['error' => 'Conversation request is too large.']);
    exit;
}
$input = json_decode($rawInput, true);

if (!is_array($input) || !is_string($input['message'] ?? null) || trim($input['message']) === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit;
}

$message = trim($input['message']);
if (mb_strlen($message) > 4000 || (array_key_exists('stream', $input) && !is_bool($input['stream']))) {
    http_response_code(400);
    echo json_encode(['error' => 'Please send a message of at most 4000 characters and a valid stream option.']);
    exit;
}
$stream = ($streamBenChat ?? false) || ($input['stream'] ?? false);
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
// Allow the bounded primary wait plus one fallback without PHP ending the request early.
set_time_limit(50);

// Call Gemini AI
$controller = new GeminiAiController();
$knowledge = [];
$started = hrtime(true);
$search = $message;
if (mb_strlen($message) < 120) {
    $recentQuestions = array_filter(array_slice($conversationHistory, -4), static fn($entry) => in_array($entry['role'], ['user', 'student'], true));
    $previous = array_pop($recentQuestions);
    if ($previous !== null) $search .= ' ' . mb_substr($previous['message'], 0, 300);
}
try {
    $knowledge = (new AdminWorkspace())->publishedKnowledge($search);
} catch (Throwable $error) {
    error_log('Published knowledge unavailable: ' . $error->getMessage());
}
$knowledgeMs = round((hrtime(true) - $started) / 1000000);
header('Server-Timing: knowledge;dur=' . $knowledgeMs);
$context = ['history' => $conversationHistory, 'knowledge' => $knowledge];

if ($stream) {
    header('Content-Type: text/event-stream; charset=UTF-8');
    header('Cache-Control: no-store, no-transform');
    header('X-Accel-Buffering: no');
    ini_set('zlib.output_compression', '0');
    while (ob_get_level() > 0) ob_end_clean();
    $emit = static function (string $event, array $data): void {
        echo 'event: ' . $event . "\n";
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n\n";
        flush();
    };
    $emit('start', ['started' => true]);
    $firstTextMs = null;
    $result = $controller->generateResponse($message, $context, static function (string $text) use ($emit, $started, &$firstTextMs): void {
        if (connection_aborted()) throw new RuntimeException('Chat client disconnected.');
        $firstTextMs ??= round((hrtime(true) - $started) / 1000000);
        $emit('delta', ['text' => $text]);
    });
    $result['timing'] = ['knowledge_ms' => $knowledgeMs, 'first_text_ms' => $firstTextMs,
        'total_ms' => round((hrtime(true) - $started) / 1000000)];
    if (filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN)) {
        error_log('Ben chat timing: ' . json_encode($result['timing']));
    }
    $emit($result['success'] ? 'done' : 'error', $result);
    exit;
}

// Generate AI response
$result = $controller->generateResponse($message, $context);

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
