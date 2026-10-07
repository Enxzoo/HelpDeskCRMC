<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/config/env.php';
require_once __DIR__ . '/../app/services/BackupService.php';

$options = getopt('', ['output:', 'mysqldump:', 'keep:', 'if-due:']);
$root = dirname(__DIR__);
$dump = $options['mysqldump'] ?? null;
if ($dump === null) {
    $matches = glob(dirname($root, 2) . '/bin/mysql/*/bin/mysqldump.exe') ?: [];
    natsort($matches);
    $dump = end($matches) ?: 'mysqldump';
}
try {
    foreach (['keep', 'if-due'] as $number) {
        if (isset($options[$number]) && !ctype_digit($options[$number])) throw new RuntimeException('Invalid numeric backup option.');
    }
    $service = new BackupService(['root' => $root,
        'output' => $options['output'] ?? dirname($root, 2) . '/backups/helpdeskcrmc', 'mysqldump' => $dump,
        'host' => env('DB_HOST', '127.0.0.1'), 'user' => env('DB_USER', 'root'),
        'password' => env('DB_PASS', ''), 'database' => env('DB_NAME', 'helpdeskcrmc')]);
    echo json_encode($service->run((int)($options['keep'] ?? 14), (int)($options['if-due'] ?? 0)), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    // Do not echo credentials, database contents, or SQL errors into scheduled-task output.
    fwrite(STDERR, 'Backup failed: check MySQL is running, the backup paths are accessible, and registered attachments exist.' . PHP_EOL);
    exit(1);
}
