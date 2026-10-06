<?php

function dev_locator_enabled(): bool
{
    if (PHP_SAPI === 'cli') {
        return false;
    }

    if (!function_exists('env')) {
        require_once __DIR__ . '/../config/env.php';
    }

    $isDebug = filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
    $isLocal = strtolower((string) env('APP_ENV', 'production')) === 'local';
    if (!$isDebug || !$isLocal) {
        return false;
    }

    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    if (str_contains($requestUri, '/api/')) {
        return false;
    }

    return true;
}

function dev_locator_attributes(string $file, int $line): string
{
    if (!dev_locator_enabled()) {
        return '';
    }

    return ' data-php-file="' . htmlspecialchars(str_replace('\\', '/', $file), ENT_QUOTES, 'UTF-8')
        . '" data-php-line="' . $line . '"';
}

function enable_dev_locator(): void
{
    static $enabled = false;
    if ($enabled || !dev_locator_enabled()) {
        return;
    }

    $autoload = __DIR__ . '/../../vendor/autoload.php';
    if (is_file($autoload)) {
        // Locator must receive the full page, not the tail of PHP's chunked buffer.
        ob_start(null, 0);
        $enabled = true;
        require_once $autoload;
    }
}

enable_dev_locator();
