<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../app/config/env.php';
require_once __DIR__ . '/../app/controllers/ConcernUrgencyClassifier.php';
require_once __DIR__ . '/../app/controllers/ConcernDuplicateDetector.php';

set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$groqChecks = 0;
function checkGroq(bool $condition, string $message): void
{
    global $groqChecks;
    if (!$condition) throw new RuntimeException($message);
    $groqChecks++;
}
function groqEnvelope(array $payload): array
{
    return ['status' => 200, 'body' => json_encode(['choices' => [[
        'finish_reason' => 'stop',
        'message' => ['content' => json_encode($payload, JSON_THROW_ON_ERROR)],
    ]]], JSON_THROW_ON_ERROR)];
}
function expectGroqFailure(Closure $action): string
{
    try {
        $action();
    } catch (Throwable $exception) {
        checkGroq(true, 'Expected failure.');
        return $exception->getMessage();
    }
    throw new RuntimeException('Invalid Groq response was accepted.');
}

try {
    $validUrgency = ['priority' => 'High', 'reason' => 'Registration closes soon.', 'confidence' => 0.94];
    $captured = [];
    $client = new GroqAiClient('test-only-key', static function ($url, $body, $headers) use (&$captured, $validUrgency) {
        $captured = compact('url', 'body', 'headers');
        return groqEnvelope($validUrgency);
    });
    $classifier = new ConcernUrgencyClassifier(fn($body) => $client->complete($body), '');
    $result = $classifier->classify('Payment deadline', 'My ledger is missing a payment before registration closes.');
    checkGroq($result['source'] === 'ai' && $result['priority'] === 'High', 'Groq urgency result was not accepted.');
    checkGroq($result['confidence'] === 0.94, 'Urgency confidence changed.');
    checkGroq($captured['url'] === 'https://api.groq.com/openai/v1/chat/completions', 'Wrong Groq endpoint.');
    checkGroq(!str_contains($captured['url'], 'test-only-key'), 'Credential leaked into URL.');
    checkGroq(in_array('Authorization: Bearer test-only-key', $captured['headers'], true), 'Bearer authentication missing.');
    checkGroq($captured['body']['model'] === GROQ_TRIAGE_MODEL, 'Groq model configuration was ignored.');
    checkGroq($captured['body']['reasoning_effort'] === 'low' && $captured['body']['max_completion_tokens'] === 1024,
        'Reasoning configuration is incorrect.');
    checkGroq(array_column($captured['body']['messages'], 'role') === ['system', 'user'], 'Invalid Groq message roles.');
    $format = $captured['body']['response_format'];
    checkGroq($format['type'] === 'json_schema' && $format['json_schema']['strict'] === true, 'Strict JSON output missing.');
    checkGroq($format['json_schema']['schema']['additionalProperties'] === false, 'Schema permits unexpected fields.');
    checkGroq($format['json_schema']['schema']['required'] === ['priority', 'reason', 'confidence'], 'Urgency schema fields missing.');
    checkGroq(!isset($captured['body']['generationConfig'], $captured['body']['contents']), 'Gemini fields sent to Groq.');

    $attempts = 0;
    $retryClient = new GroqAiClient('test-only-key', static function () use (&$attempts, $validUrgency) {
        return ++$attempts === 1 ? ['status' => 503, 'body' => '{}'] : groqEnvelope($validUrgency);
    });
    checkGroq(json_decode($retryClient->complete([]), true) === $validUrgency && $attempts === 2, 'Transient server error was not retried.');
    foreach ([400, 401, 403, 404, 429, 503] as $status) {
        $attempts = 0;
        $failingClient = new GroqAiClient('test-only-key', static function () use (&$attempts, $status) {
            $attempts++;
            return ['status' => $status, 'body' => 'provider echoed test-only-key and private concern'];
        });
        $error = expectGroqFailure(fn() => $failingClient->complete([]));
        checkGroq($attempts === ($status === 503 ? 2 : 1), 'Wrong retry policy for HTTP ' . $status);
        checkGroq(!str_contains($error, 'test-only-key') && !str_contains($error, 'private concern'), 'Provider error leaked sensitive data.');
    }
    foreach ([
        ['status' => 200, 'body' => '{invalid'],
        ['status' => 200, 'body' => '{"choices":[]}'],
        ['status' => 200, 'body' => '{"choices":[{"finish_reason":"length","message":{"content":"{}"}}]}'],
        ['status' => 200, 'body' => '{"choices":[{"finish_reason":"stop","message":{"content":"{}","refusal":"blocked"}}]}'],
        ['status' => 200, 'body' => '{"choices":[{"finish_reason":"stop","message":{"content":false}}]}'],
        ['status' => 200, 'body' => '{"choices":[{"finish_reason":"stop","message":{"content":" "}}]}'],
        ['status' => '200', 'body' => '{}'],
    ] as $envelope) {
        expectGroqFailure(fn() => (new GroqAiClient('test-only-key', fn() => $envelope))->complete([]));
    }
    expectGroqFailure(fn() => (new GroqAiClient(''))->complete([]));
    $missingKey = new ConcernUrgencyClassifier(null, '');
    checkGroq($missingKey->classify('Payment', 'The payment deadline today is approaching.')['priority'] === 'High',
        'Missing key must retain deadline fallback.');
    checkGroq($missingKey->classify('Hours', 'What are the office hours?')['source'] === 'rule_fallback', 'Missing key must use fallback.');
    $safety = (new ConcernUrgencyClassifier(static function () { throw new RuntimeException('Safety rule must skip API.'); }, ''))
        ->classify('Emergency', 'A student is unconscious.');
    checkGroq($safety['source'] === 'safety_rule' && $safety['priority'] === 'Critical/Urgent', 'Immediate safety rule was lost.');
    foreach ([
        '{invalid', '[]', '{"priority":"Unknown","reason":"Reason","confidence":0.9}',
        '{"priority":"High","reason":"Reason","confidence":1.1}',
        '{"priority":"High","reason":"Reason","confidence":-1}',
        '{"priority":"High","reason":"","confidence":0.9}',
        '{"priority":"High","reason":"Reason","confidence":0.2}',
        '{"priority":"High","reason":"Reason"}',
    ] as $payload) {
        $fallback = (new ConcernUrgencyClassifier(fn() => $payload, ''))->classify('Other request', 'Please check my document.');
        checkGroq($fallback['source'] === 'rule_fallback', 'Invalid or uncertain urgency result must fall back.');
    }

    $candidates = [['inquiry_id' => 42, 'subject' => 'Transcript request', 'description' => 'I need a certified transcript showing grades.']];
    $duplicateClient = new GroqAiClient('test-only-key', static function ($url, $body, $headers) use (&$captured) {
        $captured = compact('url', 'body', 'headers');
        return groqEnvelope(['candidate_number' => 1, 'confidence' => 0.92]);
    });
    $detector = new ConcernDuplicateDetector(fn($body) => $duplicateClient->complete($body), '');
    $match = $detector->findMatch('Academic record', 'Please release the official list of my college marks.', $candidates);
    checkGroq($match === ['inquiry_id' => 42, 'confidence' => 0.92], 'Groq semantic duplicate was not mapped to inquiry ID.');
    checkGroq($captured['body']['response_format']['json_schema']['name'] === 'concern_duplicate_match', 'Duplicate schema missing.');
    $priorText = explode("Prior concerns:\n", $captured['body']['messages'][1]['content'])[1];
    $prior = json_decode($priorText, true, 8, JSON_THROW_ON_ERROR);
    checkGroq(!isset($prior[0]['inquiry_id']) && $prior[0]['candidate_number'] === 1, 'Provider should receive only anonymous candidate numbers.');
    $manyCandidates = array_fill(0, 12, $candidates[0]);
    $detector->findMatch('Academic record', 'Please release the official list of my college marks.', $manyCandidates);
    $priorText = explode("Prior concerns:\n", $captured['body']['messages'][1]['content'])[1];
    checkGroq(count(json_decode($priorText, true)) === 8, 'Duplicate candidate limit was lost.');
    $localDetector = new ConcernDuplicateDetector(static function () { throw new RuntimeException('Local match should skip API.'); }, '');
    checkGroq($localDetector->findMatch('Transcript request', 'I need a certified transcript showing grades.', $candidates)['confidence'] === 1.0,
        'Exact duplicate guard was lost.');
    checkGroq($localDetector->findMatch('Anything', 'Another issue', []) === null, 'Empty candidates must skip API.');
    checkGroq((new ConcernDuplicateDetector(null, ''))->findMatch('Academic record', 'Please release the official list of my college marks.', $candidates) === null,
        'Missing duplicate API key must not block submission.');
    foreach ([
        '{invalid', '[]', '{"candidate_number":0,"confidence":0.99}',
        '{"candidate_number":1,"confidence":0.3}', '{"candidate_number":2,"confidence":0.99}',
        '{"candidate_number":-1,"confidence":0.99}', '{"candidate_number":1,"confidence":1.1}',
        '{"candidate_number":"1","confidence":0.99}',
    ] as $payload) {
        checkGroq((new ConcernDuplicateDetector(fn() => $payload, ''))->findMatch('Academic record',
            'Please release the official list of my college marks.', $candidates) === null, 'Invalid duplicate result must fail open.');
    }
    echo 'Groq regression checks passed: ' . $groqChecks . PHP_EOL;
} finally {
    restore_error_handler();
}
