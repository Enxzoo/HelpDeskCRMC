<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/controllers/GeminiAiController.php';

$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException($message);
    $checks++;
};
$mode = $argv[1] ?? 'default';
if (in_array($mode, ['disabled', 'duplicate'], true)) {
    define('GEMINI_FALLBACK_MODEL', $mode === 'disabled' ? '' : 'gemini-3.6-flash');
    $calls = 0;
    $controller = new GeminiAiController(static function () use (&$calls): string {
        $calls++;
        throw new GeminiTransientException('Unavailable', 503);
    }, 'test-key');
    $result = $controller->generateResponse('Hello');
    $check(!$result['success'] && $calls === 1, 'Disabled or duplicate fallback retried the primary.');
    echo "Ben fallback $mode configuration passed.\n";
    exit;
}

$httpFailure = new ReflectionMethod(GeminiAiController::class, 'httpFailure');
$connectionFailure = new ReflectionMethod(GeminiAiController::class, 'connectionFailure');
$responseText = new ReflectionMethod(GeminiAiController::class, 'responseText');
$quotaViolation = [
    'quotaMetric' => 'generativelanguage.googleapis.com/generate_content_free_tier_requests',
    'quotaId' => 'GenerateRequestsPerDayPerProjectPerModel-FreeTier',
    'quotaDimensions' => ['location' => 'global', 'model' => 'gemini-3.6-flash'],
    'quotaValue' => '20',
];
$modelQuotaError = ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED', 'details' => [[
    '@type' => 'type.googleapis.com/google.rpc.QuotaFailure', 'violations' => [$quotaViolation],
]]];
$context = [
    'history' => [['role' => 'user', 'message' => 'Asa ko mobayad?'], ['role' => 'model', 'message' => 'Sa cashier.']],
    'knowledge' => [['title' => 'Payment reference', 'content' => 'Ask the cashier about payment arrangements.']],
];
$requests = [];
$controller = new GeminiAiController(static function ($url, $body) use (&$requests): string {
    $requests[] = [$url, $body];
    return 'Primary answer';
}, 'test-key');
$result = $controller->generateResponse('Hello', $context);
$check($result['success'] && $result['answer'] === 'Primary answer' && count($requests) === 1, 'Healthy primary unnecessarily used fallback.');
$check(str_contains($requests[0][0], '/gemini-3.6-flash:generateContent?'), 'Primary model changed.');

$modelQuotaFailure = $httpFailure->invoke(null, 429, $modelQuotaError, 'gemini-3.6-flash');
$check($modelQuotaFailure instanceof GeminiTransientException, 'Confirmed per-model daily quota did not permit fallback.');
$retryable = [$modelQuotaFailure];
$check(!($httpFailure->invoke(null, 429, $modelQuotaError, 'gemini-3.5-flash') instanceof GeminiTransientException), 'Quota for another model permitted fallback.');
foreach ([
    array_replace($quotaViolation, ['quotaId' => 'GenerateRequestsPerDayPerProject-FreeTier']),
    array_replace($quotaViolation, ['quotaDimensions' => ['location' => 'global']]),
] as $projectQuota) {
    $error = $modelQuotaError;
    $error['details'][0]['violations'][] = $projectQuota;
    $check(!($httpFailure->invoke(null, 429, $error, 'gemini-3.6-flash') instanceof GeminiTransientException), 'Mixed model/project quota permitted fallback.');
}
$error = $modelQuotaError;
$error['details'][0]['violations'] = [];
$check(!($httpFailure->invoke(null, 429, $error, 'gemini-3.6-flash') instanceof GeminiTransientException), 'Missing quota scope permitted fallback.');
foreach ([408, 500, 502, 503, 504] as $status) {
    $failure = $httpFailure->invoke(null, $status);
    $check($failure instanceof GeminiTransientException && $failure->getCode() === $status, 'Transient HTTP error was not classified correctly.');
    $retryable[] = $failure;
}
foreach ([CURLE_OPERATION_TIMEDOUT, CURLE_COULDNT_CONNECT, CURLE_COULDNT_RESOLVE_HOST, CURLE_PARTIAL_FILE, CURLE_RECV_ERROR] as $code) {
    $failure = $connectionFailure->invoke(null, $code);
    $check($failure instanceof GeminiTransientException, 'Transient connection error was not classified correctly.');
    $retryable[] = $failure;
}
foreach ($retryable as $failure) {
    $requests = [];
    $controller = new GeminiAiController(static function ($url, $body) use (&$requests, $failure): string {
        $requests[] = [$url, $body];
        if (count($requests) === 1) throw $failure;
        return 'Fallback answer';
    }, 'test-key');
    $result = $controller->generateResponse('Can I pay tomorrow kay wala pa koy kwarta?', $context);
    $check($result['success'] && $result['answer'] === 'Fallback answer' && count($requests) === 2, 'Transient failure did not use exactly one fallback.');
    $check(str_contains($requests[1][0], '/gemini-3.5-flash:generateContent?'), 'Fallback called the wrong model.');
    $check($requests[0][1] === $requests[1][1], 'Fallback lost conversation, language instructions, safety settings, or school context.');
}

foreach ([400, 401, 402, 403, 404, 429, 501] as $status) {
    $failure = $httpFailure->invoke(null, $status);
    $check(!($failure instanceof GeminiTransientException), 'Permanent or quota error was considered retryable.');
    $calls = 0;
    $controller = new GeminiAiController(static function () use (&$calls, $failure): string {
        $calls++;
        throw $failure;
    }, 'test-key');
    $result = $controller->generateResponse('Hello');
    $check(!$result['success'] && $calls === 1, 'Fallback bypassed a permanent or quota error.');
}
$check(!($connectionFailure->invoke(null, CURLE_SSL_CONNECT_ERROR) instanceof GeminiTransientException), 'TLS failure was treated as a model overload.');

foreach ([['promptFeedback' => ['blockReason' => 'SAFETY']],
    ['candidates' => [['finishReason' => 'SAFETY']]],
    ['candidates' => [['finishReason' => 'MAX_TOKENS']]]] as $blocked) {
    $calls = 0;
    $controller = new GeminiAiController(static function () use (&$calls, $blocked, $responseText): string {
        $calls++;
        return $responseText->invoke(null, $blocked);
    }, 'test-key');
    $result = $controller->generateResponse('Hello');
    $check(!$result['success'] && $calls === 1, 'Safety or incomplete response triggered another model.');
}

$requests = $chunks = [];
$controller = new GeminiAiController(static function ($url, $body, $onChunk) use (&$requests): string {
    $requests[] = [$url, $body];
    if (count($requests) === 1) {
        $onChunk('');
        throw new GeminiTransientException('First reply timed out');
    }
    $onChunk('Sige, ');
    $onChunk('tabangan tika.');
    return 'Sige, tabangan tika.';
}, 'test-key');
$result = $controller->generateResponse('Tabangi ko', $context, static function ($text) use (&$chunks): void { $chunks[] = $text; });
$check($result['success'] && $result['answer'] === implode('', $chunks), 'Fallback streaming lost or duplicated text.');
$check(count($requests) === 2 && str_contains($requests[1][0], '/gemini-3.5-flash:streamGenerateContent?alt=sse&'), 'Streaming fallback used the wrong endpoint.');
$check($requests[0][1] === $requests[1][1], 'Streaming fallback lost school context or history.');

$parser = new ReflectionMethod(GeminiAiController::class, 'consumeStream');
foreach ([['code' => 503, 'status' => 'UNAVAILABLE'], $modelQuotaError] as $streamError) {
    $calls = 0;
    $controller = new GeminiAiController(static function ($url, $body, $onChunk) use (&$calls, $parser, $streamError): string {
        $calls++;
        $buffer = $calls === 1
            ? 'data: ' . json_encode(['error' => $streamError]) . "\n\n"
            : "data: {\"candidates\":[{\"content\":{\"parts\":[{\"text\":\"Hello\"}]},\"finishReason\":\"STOP\"}]}\n\n";
        $answer = '';
        $complete = false;
        $parser->invokeArgs(null, [&$buffer, $onChunk, &$answer, &$complete, 'gemini-3.6-flash']);
        return $answer;
    }, 'test-key');
    $chunks = [];
    $result = $controller->generateResponse('Hello', [], static function ($text) use (&$chunks): void { $chunks[] = $text; });
    $check($result['success'] && $calls === 2 && $chunks === ['Hello'], 'An overload or model quota reported inside a stream did not use fallback.');
}

$calls = 0;
$chunks = [];
$controller = new GeminiAiController(static function ($url, $body, $onChunk) use (&$calls): string {
    $calls++;
    $onChunk('Partial answer');
    throw new GeminiTransientException('Stream interrupted', 503);
}, 'test-key');
$result = $controller->generateResponse('Hello', [], static function ($text) use (&$chunks): void { $chunks[] = $text; });
$check(!$result['success'] && $calls === 1 && $chunks === ['Partial answer'], 'Fallback mixed two models after streaming began.');

$calls = 0;
$controller = new GeminiAiController(static function () use (&$calls): string {
    $calls++;
    throw new GeminiTransientException('Both models unavailable', 503);
}, 'test-key');
$result = $controller->generateResponse('Hello');
$check(!$result['success'] && $calls === 2, 'Both-model failure exceeded the retry limit.');
$check(str_contains($result['error'], 'submit your concern to staff') && !str_contains($result['error'], 'test-key'), 'Both-model failure lost the safe staff assistance message.');

$calls = 0;
$controller = new GeminiAiController(static function () use (&$calls): string { $calls++; return 'Unexpected'; }, '');
$result = $controller->generateResponse('Hello');
$check(!$result['success'] && $calls === 0, 'Missing credentials called a model.');

echo "Ben fallback regression passed: $checks checks.\n";
