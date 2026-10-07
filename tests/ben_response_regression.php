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

require_once __DIR__ . '/../app/models/AdminWorkspace.php';
$check = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
foreach ([
    ['Asa ko mobayad sa tuition?', 'payment'],
    ['Wala pa akong grado', 'grades'],
    ['Naa bay iskolar para nako?', 'scholarship'],
    ['Nawala akong ID', 'replacement'],
    ['Dili ko ka-login sa portal', 'portal'],
    ['Can I pay tomorrow kay wala pa koy kwarta?', 'cashier'],
] as [$question, $keyword]) {
    $terms = AdminWorkspace::knowledgeSearchTerms($question);
    $check(str_starts_with($terms, $question) && str_contains($terms, $keyword), 'Bilingual lookup lost the message or its English keywords.');
}
$check(AdminWorkspace::knowledgeSearchTerms('paymentbayadword') === 'paymentbayadword', 'Lookup matched part of an unrelated word.');
$history = [];
for ($index = 0; $index < 40; $index++) {
    $history[] = ['role' => $index % 2 === 0 ? 'user' : 'model', 'message' => 'TURN-' . $index . ' ' . str_repeat('x', 4000)];
}
$knowledge = array_fill(0, 8, ['title' => 'School reference', 'content' => str_repeat('k', 6000)]);
$result = $controller->generateResponse('Dili ko ka-login sa portal', ['history' => $history, 'knowledge' => $knowledge]);
$prompt = $body['systemInstruction']['parts'][0]['text'];
$check($result['success'] && str_contains($prompt, 'Cebuano/Bisaya') && str_contains($prompt, 'Cebuano is not Tagalog'), 'Ben lost the multilingual instructions.');
$check(str_contains($prompt, '2-4 short sentences') && str_contains($prompt, 'informal spelling'), 'Ben lost the concise or informal-language guidance.');
$check(count($body['contents']) <= 14 && !str_contains(json_encode($body['contents']), 'TURN-0 '), 'Unbounded or very old history reached Gemini.');
$check(str_contains(json_encode($body['contents']), 'TURN-39 ') && str_contains(json_encode($body['contents']), 'Earlier conversation excerpts'), 'Recent history or bounded earlier context is missing.');
$check(strlen($prompt) < 21000 && substr_count($prompt, str_repeat('k', 1800)) === 4, 'Knowledge reference budget was not enforced.');
$check(end($body['contents'])['parts'][0]['text'] === 'Dili ko ka-login sa portal', 'The original Cebuano question was replaced.');
$frames = ": keepalive\n\n";
$firstCandidate = ['content' => ['parts' => [['thought' => true, 'text' => 'private reasoning'], ['text' => 'Sige, ']]]];
$lastCandidate = ['content' => ['parts' => [['text' => 'tabangan tika.']]], 'finishReason' => 'STOP'];
$frames .= 'data: ' . json_encode(['candidates' => [$firstCandidate]]) . "\r\n\r\n";
$frames .= 'data: ' . json_encode(['candidates' => [$lastCandidate]]) . "\n\n";
$parser = new ReflectionMethod(GeminiAiController::class, 'consumeStream');
$buffer = $streamed = '';
$complete = false;
$chunks = [];
$callback = static function (string $text) use (&$chunks): void { $chunks[] = $text; };
foreach (str_split($frames, 3) as $fragment) {
    $buffer .= $fragment;
    $parser->invokeArgs(null, [&$buffer, $callback, &$streamed, &$complete]);
}
$check($complete && $buffer === '' && $streamed === 'Sige, tabangan tika.' && count($chunks) === 2, 'Fragmented Gemini events were not streamed correctly.');
foreach ([['error' => ['message' => 'provider error']], ['promptFeedback' => ['blockReason' => 'SAFETY']],
    ['candidates' => [['finishReason' => 'MAX_TOKENS']]], ['candidates' => [['finishReason' => 'SAFETY']]]] as $failure) {
    $buffer = 'data: ' . json_encode($failure) . "\n\n";
    try {
        $parser->invokeArgs(null, [&$buffer, $callback, &$streamed, &$complete]);
        throw new LogicException('An incomplete or blocked response was accepted.');
    } catch (RuntimeException $expected) {
    }
}
$controller = new GeminiAiController(static function ($url, $request, $onChunk) use ($check): string {
    $check(str_contains($url, ':streamGenerateContent?alt=sse&'), 'Streaming used the non-streaming Gemini endpoint.');
    $onChunk('Hello');
    $onChunk(' there');
    return 'Hello there';
}, 'test-key');
$chunks = [];
$result = $controller->generateResponse('Hello', [], $callback);
$check($result['success'] && $result['answer'] === implode('', $chunks), 'Streaming broke the existing final-answer contract.');
echo "Multilingual, bounded-context, and Gemini stream regression checks passed.\n";
