<?php
require_once __DIR__ . '/../app/config/env.php';
require_once __DIR__ . '/../app/controllers/GeminiAiController.php';

header('Content-Type: text/html; charset=utf-8');
?>
<title>Gemini API Direct Test</title>
<style>
body {
  font-family: system-ui, sans-serif;
  max-width: 900px;
  margin: 40px auto;
  padding: 20px;
  background: #fbf6ee;
  color: #1c1b18;
}
h1 { margin-top: 0; }
.box {
  background: #fff;
  border: 1px solid #ddd;
  border-radius: 8px;
  padding: 20px;
  margin: 20px 0;
}
.box.error { background: #fee; border-color: #dc2626; }
.box.success { background: #d1fae5; border-color: #10b981; }
pre {
  background: #f8f9fa;
  border: 1px solid #ddd;
  border-radius: 4px;
  padding: 12px;
  overflow-x: auto;
  font-size: 13px;
  line-height: 1.5;
}
.label { font-weight: 600; margin-top: 12px; }
</style>

<h1>Gemini API Direct Test</h1>

<?php
$controller = new GeminiAiController();

echo '<div class="box">';
echo '<h2>Step 1: Check Configuration</h2>';

$isConfigured = $controller->isConfigured();
echo '<div class="label">API Key Configured:</div>';
echo '<pre>' . ($isConfigured ? 'YES ✓' : 'NO ✗') . '</pre>';

if (defined('GEMINI_API_KEY')) {
    $key = GEMINI_API_KEY;
    echo '<div class="label">API Key Value:</div>';
    echo '<pre>' . substr($key, 0, 15) . '...' . substr($key, -8) . '</pre>';
    echo '<div class="label">Key Length:</div>';
    echo '<pre>' . strlen($key) . ' characters</pre>';
} else {
    echo '<div class="label">ERROR:</div>';
    echo '<pre>GEMINI_API_KEY constant not defined in env.php</pre>';
}
echo '</div>';

if (!$isConfigured) {
    echo '<div class="box error">';
    echo '<h2>Cannot Test</h2>';
    echo '<p>API key is not configured. Cannot proceed with test.</p>';
    echo '</div>';
    exit;
}

echo '<div class="box">';
echo '<h2>Step 2: Test Simple Message (No History)</h2>';

try {
    $result = $controller->generateResponse('hello', []);

    if ($result['success']) {
        echo '<div class="box success">';
        echo '<div class="label">✓ SUCCESS</div>';
        echo '<div class="label">Response:</div>';
        echo '<pre>' . htmlspecialchars($result['answer']) . '</pre>';
        echo '</div>';
    } else {
        echo '<div class="box error">';
        echo '<div class="label">✗ FAILED</div>';
        echo '<div class="label">Error:</div>';
        echo '<pre>' . htmlspecialchars($result['error'] ?? 'Unknown error') . '</pre>';
        echo '<div class="label">Fallback:</div>';
        echo '<pre>' . ($result['fallback'] ? 'YES' : 'NO') . '</pre>';
        echo '</div>';
    }

    echo '<div class="label">Full Result:</div>';
    echo '<pre>' . htmlspecialchars(print_r($result, true)) . '</pre>';

} catch (Exception $e) {
    echo '<div class="box error">';
    echo '<div class="label">✗ EXCEPTION</div>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    echo '</div>';
}
echo '</div>';

echo '<div class="box">';
echo '<h2>Step 3: Test With Conversation History</h2>';

$history = [
    ['role' => 'user', 'message' => 'hello'],
    ['role' => 'model', 'message' => 'Hi! How can I help you today?']
];

echo '<div class="label">History Being Sent:</div>';
echo '<pre>' . htmlspecialchars(json_encode($history, JSON_PRETTY_PRINT)) . '</pre>';

try {
    $result = $controller->generateResponse('can you help me with registrar?', [
        'history' => $history
    ]);

    if ($result['success']) {
        echo '<div class="box success">';
        echo '<div class="label">✓ SUCCESS</div>';
        echo '<div class="label">Response:</div>';
        echo '<pre>' . htmlspecialchars($result['answer']) . '</pre>';
        echo '</div>';
    } else {
        echo '<div class="box error">';
        echo '<div class="label">✗ FAILED</div>';
        echo '<div class="label">Error:</div>';
        echo '<pre>' . htmlspecialchars($result['error'] ?? 'Unknown error') . '</pre>';
        echo '<div class="label">Fallback:</div>';
        echo '<pre>' . ($result['fallback'] ? 'YES' : 'NO') . '</pre>';
        echo '</div>';
    }

    echo '<div class="label">Full Result:</div>';
    echo '<pre>' . htmlspecialchars(print_r($result, true)) . '</pre>';

} catch (Exception $e) {
    echo '<div class="box error">';
    echo '<div class="label">✗ EXCEPTION</div>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    echo '</div>';
}
echo '</div>';
?>