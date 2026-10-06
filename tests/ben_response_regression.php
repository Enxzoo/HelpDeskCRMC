<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/controllers/GeminiAiController.php';
$body = [];
$answer = 'Your original natural-language answer. Did that answer your concern?';
$controller = new GeminiAiController(function ($url, $request) use (&$body, $answer) {
    $body = $request;
    return $answer;
}, 'test-key');
$result = $controller->generateResponse('How do I request a transcript?', ['history' => [['role' => 'user', 'message' => 'Hello']]]);
$checks = [
    $result['success'], $result['answer'] === $answer,
    !isset($result['action']) && !isset($result['quick_replies']),
    !isset($body['generationConfig']['responseSchema']),
    !isset($body['generationConfig']['responseMimeType']),
    $body['generationConfig']['maxOutputTokens'] === 1000,
    str_contains($body['systemInstruction']['parts'][0]['text'], 'YOUR KNOWLEDGE BASE (School Policies & Procedures)'),
    str_contains($body['systemInstruction']['parts'][0]['text'], 'Did that answer your concern?'),
];
foreach ($checks as $passed) if (!$passed) throw new RuntimeException('The previous Ben response contract changed.');
echo 'Original Ben response checks passed: ' . count($checks) . PHP_EOL;
