<?php
if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}
$testDatabase = getenv('HELPDESK_TEST_DB');
if (!is_string($testDatabase) || !preg_match('/^helpdeskcrmc_test_[a-f0-9]{12}$/', $testDatabase)) {
    http_response_code(503);
    exit;
}
require_once __DIR__ . '/../app/config/env.php';
putenv('DB_NAME=' . $testDatabase);
$sessionDirectory = __DIR__ . '/.runtime';
if (!is_dir($sessionDirectory)) {
    mkdir($sessionDirectory, 0700, true);
}
session_save_path($sessionDirectory);
$publicDirectory = realpath(__DIR__ . '/../public');
$requestPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$target = realpath($publicDirectory . ($requestPath === '/' ? '/index.php' : $requestPath));
if ($target === false || !str_starts_with($target, $publicDirectory . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    return true;
}
if (pathinfo($target, PATHINFO_EXTENSION) !== 'php') {
    return false;
}
require $target;
return true;
