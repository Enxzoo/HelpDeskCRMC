<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/config/env.php';
require_once __DIR__ . '/../app/services/BackupService.php';
if (!in_array(env('DB_HOST', '127.0.0.1'), ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Backup regression requires a local test server.');
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$host = env('DB_HOST', '127.0.0.1');
$user = env('DB_USER', 'root');
$password = env('DB_PASS', '');
$server = new mysqli($host, $user, $password);
$id = bin2hex(random_bytes(6));
$sourceName = 'helpdesk_backup_test_' . $id;
$restoreName = $sourceName . '_restore';
$runtime = __DIR__ . '/.runtime';
if (!is_dir($runtime)) mkdir($runtime, 0700, true);
$fixture = $runtime . '/backup-' . $id;
$created = [];
$checks = 0;
$check = static function (bool $value, string $message) use (&$checks): void {
    if (!$value) throw new RuntimeException($message);
    $checks++;
};
$source = $restored = null;
try {
    foreach ([$sourceName, $restoreName] as $name) {
        $server->query('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4');
        $created[] = $name;
    }
    foreach (['public', 'storage/attachments', 'backups', 'restored'] as $dir) mkdir($fixture . '/' . $dir, 0700, true);
    $source = new mysqli($host, $user, $password, $sourceName);
    $source->set_charset('utf8mb4');
    $source->query('CREATE TABLE inquiries (id INT PRIMARY KEY, description TEXT, sample LONGBLOB) ENGINE=InnoDB');
    $source->query('CREATE TABLE replies (id INT PRIMARY KEY, inquiry_id INT, message TEXT,
        FOREIGN KEY (inquiry_id) REFERENCES inquiries(id)) ENGINE=InnoDB');
    $source->query('CREATE TABLE inquiry_attachments (attachment_id INT PRIMARY KEY, inquiry_id INT NULL,
        file_path VARCHAR(255), file_size INT) ENGINE=InnoDB');
    $description = "Cebuano: dili ko ka-login. Unicode: \u{00F1} \u{1F393}. Quotes: '\"\\";
    $sample = "binary\0data\xFF";
    $insert = $source->prepare('INSERT INTO inquiries VALUES (1, ?, ?)');
    $insert->bind_param('ss', $description, $sample);
    $insert->execute();
    $source->query('INSERT INTO replies VALUES (1, 1, "Staff response")');
    $relative = 'storage/attachments/' . str_repeat('a', 32) . '.pdf';
    $contents = "%PDF-1.4\nTest attachment\n";
    file_put_contents($fixture . '/' . $relative, $contents);
    $size = strlen($contents);
    $source->query("INSERT INTO inquiry_attachments VALUES (1, 1, '$relative', $size)");
    $dumps = glob(dirname(__DIR__, 3) . '/bin/mysql/*/bin/mysqldump.exe') ?: [];
    natsort($dumps);
    $config = ['root' => $fixture, 'output' => $fixture . '/backups', 'mysqldump' => end($dumps),
        'host' => $host, 'user' => $user, 'password' => $password, 'database' => $sourceName];
    $service = new BackupService($config);
    $first = $service->run();
    $archive = $config['output'] . '/' . $first['archive'];
    $manifest = BackupService::verify($archive);
    $check($first['success'] && $manifest['database'] === $sourceName, 'Backup did not complete.');
    $check($manifest['attachment_records'] === 1, 'Attachment record not captured.');
    $check(count($manifest['files']) === 2, 'Unexpected archive contents.');
    $check(!isset((new PharData($archive))['client.cnf']), 'Database credentials leaked into archive.');
    $zip = new PharData($archive);
    $zip->extractTo($fixture . '/restored');
    $check(file_get_contents($fixture . '/restored/' . $relative) === $contents, 'Attachment restore differs.');
    $restored = new mysqli($host, $user, $password, $restoreName);
    $restored->set_charset('utf8mb4');
    $restored->multi_query(file_get_contents($fixture . '/restored/database.sql'));
    do {
        $result = $restored->store_result();
        if ($result) $result->free();
    } while ($restored->more_results() && $restored->next_result());
    $row = $restored->query('SELECT * FROM inquiries WHERE id = 1')->fetch_assoc();
    $check($row['description'] === $description && $row['sample'] === $sample, 'Unicode/binary database restore differs.');
    $check($restored->query('SELECT COUNT(*) AS n FROM replies JOIN inquiries ON inquiries.id = replies.inquiry_id')->fetch_assoc()['n'] == 1,
        'Restored concern/reply relationships differ.');
    $check(!empty($service->run(14, 4)['skipped']), 'Recent backup was not skipped.');
    $lock = fopen($config['output'] . '/backup.lock', 'c');
    flock($lock, LOCK_EX);
    $check(!empty($service->run()['skipped']), 'Overlapping backup was not skipped.');
    flock($lock, LOCK_UN);
    fclose($lock);
    file_put_contents($config['output'] . '/unrelated.zip', 'Do not delete');
    $second = $service->run(1);
    $check(is_file($config['output'] . '/' . $second['archive']), 'Retention deleted the newest backup.');
    $check(count(glob($config['output'] . '/helpdesk-*.zip')) === 1, 'Retention did not prune old completed backups.');
    $check(is_file($config['output'] . '/unrelated.zip'), 'Retention touched an unrelated file.');
    unlink($fixture . '/' . $relative);
    try {
        $service->run(1);
        throw new LogicException('A missing attachment was silently ignored.');
    } catch (RuntimeException $error) {
        $check(str_contains($error->getMessage(), 'attachment'), 'Wrong missing-attachment error.');
    }
    $check(is_file($config['output'] . '/' . $second['archive']), 'Failure deleted the last valid backup.');
    $check(glob($config['output'] . '/.pending-*') === [], 'Temporary credentials/export files remained after failure.');
    $check(json_decode(file_get_contents($config['output'] . '/last-attempt.json'), true)['success'] === false,
        'Failure was not recorded.');
    $check(json_decode(file_get_contents($config['output'] . '/last-success.json'), true)['archive'] === $second['archive'],
        'Failure overwrote the last successful result.');
    copy($archive = $config['output'] . '/' . $second['archive'], $config['output'] . '/corrupt.zip');
    $corrupt = new PharData($config['output'] . '/corrupt.zip');
    $corrupt->addFromString('database.sql', 'Damaged export');
    unset($corrupt);
    try {
        BackupService::verify($config['output'] . '/corrupt.zip');
        throw new LogicException('Corruption was not detected.');
    } catch (RuntimeException $error) {
        $check(str_contains($error->getMessage(), 'integrity'), 'Wrong corruption error.');
    }
    try {
        (new BackupService(array_replace($config, ['output' => $fixture . '/public'])))->run();
        throw new LogicException('Public backup location was accepted.');
    } catch (RuntimeException $error) {
        $check(str_contains($error->getMessage(), 'private'), 'Public output rejection failed.');
    }
    $check($source->query('SELECT COUNT(*) AS n FROM inquiries')->fetch_assoc()['n'] == 1, 'Source database was modified.');
    echo "Backup export, ZIP checksums, isolated SQL/file restoration, overlap/due checks, retention and failure safety: $checks checks passed.\n";
} finally {
    unset($zip, $corrupt);
    if ($source instanceof mysqli) $source->close();
    if ($restored instanceof mysqli) $restored->close();
    foreach ($created as $name) {
        if (preg_match('/^helpdesk_backup_test_[a-f0-9]{12}(_restore)?$/D', $name)) $server->query('DROP DATABASE `' . $name . '`');
    }
    $server->close();
    $real = realpath($fixture);
    if ($real && str_starts_with($real, realpath($runtime) . DIRECTORY_SEPARATOR)) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($real);
    }
}
