<?php
/**
 * env.php
 * Minimal .env loader — no Composer dependency needed for a school project.
 * Reads /helpdeskcrmc/.env (copy .env.example to .env and fill in real values).
 */

function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        putenv(trim($key) . '=' . trim($value));
    }
}

function env(string $key, $default = null)
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

loadEnv(__DIR__ . '/../../.env');

// Define constants for easy access
define('GEMINI_API_KEY', env('GEMINI_API_KEY', ''));
$triageModel = trim((string)env('GEMINI_TRIAGE_MODEL', ''));
define('GEMINI_TRIAGE_MODEL', $triageModel !== '' ? $triageModel : 'gemini-3.6-flash');
define('GEMINI_FALLBACK_MODEL', trim((string)env('GEMINI_FALLBACK_MODEL', 'gemini-3.5-flash')));

define('GROQ_API_KEY', env('GROQ_API_KEY', ''));
$groqTriageModel = trim((string)env('GROQ_TRIAGE_MODEL', ''));
define('GROQ_TRIAGE_MODEL', $groqTriageModel !== '' ? $groqTriageModel : 'openai/gpt-oss-20b');
