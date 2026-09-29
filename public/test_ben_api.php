<?php
/**
 * Direct test of Ben's Gemini API
 */
require_once __DIR__ . '/../app/config/env.php';
require_once __DIR__ . '/../app/controllers/GeminiAiController.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Ben API Test</h1>";
echo "<pre>";

$controller = new GeminiAiController();

echo "1. Checking if configured...\n";
$isConfigured = $controller->isConfigured();
echo "   Result: " . ($isConfigured ? "YES" : "NO") . "\n\n";

if (!$isConfigured) {
    echo "ERROR: API key not configured in .env file\n";
    exit;
}

echo "2. Testing simple message: 'hello'\n";
$result = $controller->generateResponse('hello', []);

echo "   Success: " . ($result['success'] ? "YES" : "NO") . "\n";

if ($result['success']) {
    echo "   Answer: " . substr($result['answer'], 0, 200) . "...\n";
} else {
    echo "   Error: " . ($result['error'] ?? 'Unknown error') . "\n";
    echo "   Fallback: " . ($result['fallback'] ? "YES" : "NO") . "\n";
}

echo "</pre>";
