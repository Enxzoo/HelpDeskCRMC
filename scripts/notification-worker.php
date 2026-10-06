<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$opensslConfig = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';
// Windows OpenSSL reads its configuration at process startup, before putenv can help.
if (PHP_OS_FAMILY === 'Windows' && !getenv('OPENSSL_CONF') && is_file($opensslConfig)) {
    $child = proc_open([PHP_BINARY, ...$argv], [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes,
        null, ['OPENSSL_CONF' => $opensslConfig] + getenv(), ['bypass_shell' => true]);
    if (!is_resource($child)) exit(1);
    exit(proc_close($child));
}
$logDirectory = __DIR__ . '/../logs';
if (!is_dir($logDirectory)) mkdir($logDirectory, 0700, true);
ini_set('log_errors', '1');
ini_set('error_log', $logDirectory . '/notification-worker.log');
require_once __DIR__ . '/../app/config/env.php';
require_once __DIR__ . '/../app/services/NotificationDispatcher.php';
if (in_array('--loop', $argv, true)) {
    $directory = __DIR__ . '/../storage';
    if (!is_dir($directory)) mkdir($directory, 0700, true);
    $lock = fopen($directory . '/notification-worker.pid', 'c+');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) exit;
    ftruncate($lock, 0);
    fwrite($lock, (string)getmypid());
    do {
        loadEnv(__DIR__ . '/../.env');
        try { (new NotificationDispatcher())->runBatch(); }
        catch (Throwable $exception) {
            error_log('Notification worker could not process its queue; restarting is required.');
            exit(1);
        }
        sleep(3);
    } while (true);
}
echo json_encode((new NotificationDispatcher())->runBatch(), JSON_THROW_ON_ERROR) . PHP_EOL;
