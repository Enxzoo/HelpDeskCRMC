<?php

final class BackupService
{
    public function __construct(private array $config) {}

    public static function verify(string $archive): array
    {
        $zip = new PharData($archive);
        $manifest = json_decode($zip['manifest.json']->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if (($manifest['format'] ?? null) !== 1 || !is_array($manifest['files'] ?? null)) {
            throw new RuntimeException('Invalid backup manifest.');
        }
        foreach ($manifest['files'] as $name => $metadata) {
            if (!is_string($name) || str_contains($name, '..') || str_starts_with($name, '/')
                || str_contains($name, '\\') || str_contains($name, ':') || !isset($zip[$name])
                || $zip[$name]->getSize() !== $metadata['bytes']
                || hash_file('sha256', 'phar://' . str_replace('\\', '/', $archive) . '/' . $name) !== $metadata['sha256']) {
                throw new RuntimeException('Backup integrity check failed.');
            }
        }
        return $manifest;
    }

    public function run(int $keep = 14, int $dueHours = 0): array
    {
        if ($keep < 1 || $keep > 365 || $dueHours < 0 || $dueHours > 168) {
            throw new InvalidArgumentException('Invalid backup retention or interval.');
        }
        $directory = realpath($this->config['output']);
        $public = realpath($this->config['root'] . '/public');
        if (!$directory || !$public || is_link($this->config['output'])
            || self::within($directory, $public)) {
            throw new RuntimeException('Create a private backup directory outside public/ first.');
        }
        $lock = fopen($directory . '/backup.lock', 'c');
        if (!$lock) throw new RuntimeException('Cannot open the backup lock.');
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return ['skipped' => true, 'reason' => 'A backup is already running.'];
        }
        $work = null;
        $db = null;
        $tablesLocked = false;
        $zip = null;
        try {
            $latest = $this->completed($directory);
            if ($dueHours && $latest && filemtime($latest[0]) > time() - $dueHours * 3600) {
                return ['skipped' => true, 'reason' => 'A recent backup already exists.'];
            }
            if (!is_file($this->config['mysqldump'])) throw new RuntimeException('mysqldump was not found.');
            $database = $this->config['database'];
            if (!preg_match('/^[a-zA-Z0-9_]+$/D', $database)) throw new RuntimeException('Invalid database name.');
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $db = new mysqli($this->config['host'], $this->config['user'], $this->config['password'], $database);
            $db->set_charset('utf8mb4');
            $tables = $db->query('SHOW FULL TABLES')->fetch_all(MYSQLI_NUM);
            foreach ($db->query('SHOW TABLE STATUS')->fetch_all(MYSQLI_ASSOC) as $table) {
                if ($table['Engine'] !== null && $table['Engine'] !== 'InnoDB') {
                    throw new RuntimeException('Consistent backups require InnoDB tables.');
                }
            }
            $started = gmdate('c');
            $id = gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(4));
            $work = $directory . '/.pending-' . $id;
            if (!mkdir($work, 0700)) throw new RuntimeException('Cannot create backup workspace.');
            $options = "[client]\n";
            foreach (['host', 'user', 'password'] as $key) {
                $value = str_replace(['\\', '"', "\n", "\r", "\t"], ['\\\\', '\\"', '\\n', '\\r', '\\t'], $this->config[$key]);
                $options .= $key . '="' . $value . "\"\n";
            }
            self::write($work . '/client.cnf', $options);
            chmod($work . '/client.cnf', 0600);
            $db->query('SET SESSION lock_wait_timeout = 30');
            // Keep attachment metadata stable while mysqldump captures the DB and files are archived.
            $db->query('LOCK TABLES inquiry_attachments READ');
            $tablesLocked = true;
            $attachments = $db->query('SELECT file_path, file_size FROM inquiry_attachments')->fetch_all(MYSQLI_ASSOC);
            self::execute([
                $this->config['mysqldump'], '--defaults-file=' . $work . '/client.cnf',
                '--single-transaction', '--quick', '--routines', '--events', '--triggers', '--hex-blob',
                '--no-tablespaces', '--set-gtid-purged=OFF', '--default-character-set=utf8mb4',
                '--result-file=' . $work . '/database.sql', '--', $database,
            ], $work);
            if (!is_file($work . '/database.sql') || filesize($work . '/database.sql') === 0) {
                throw new RuntimeException('The database export is empty.');
            }
            $zip = new PharData($work . '/backup.zip', 0, null, Phar::ZIP);
            $files = [];
            $add = static function (string $source, string $name) use ($zip, &$files): void {
                $bytes = filesize($source);
                $sha = hash_file('sha256', $source);
                if ($bytes === false || $sha === false) throw new RuntimeException('Cannot read a backup source file.');
                $zip->addFile($source, $name);
                $files[$name] = ['bytes' => $bytes, 'sha256' => $sha];
            };
            $add($work . '/database.sql', 'database.sql');
            foreach ($attachments as $attachment) {
                $relative = $attachment['file_path'];
                if (!preg_match('~^(storage/attachments|uploads)/[a-f0-9]{32}\.[a-z0-9]+$~D', $relative)) {
                    throw new RuntimeException('An attachment has an unsupported storage path.');
                }
                $file = realpath($this->config['root'] . '/' . $relative);
                $parent = realpath($this->config['root'] . '/' . dirname($relative));
                if (!$file || !$parent || !self::within($parent, realpath($this->config['root']))
                    || !self::within($file, $parent) || is_link($this->config['root'] . '/' . $relative)
                    || !is_readable($file) || ($attachment['file_size'] !== null && filesize($file) !== (int)$attachment['file_size'])) {
                    throw new RuntimeException('A registered attachment is missing or unreadable; backup cancelled.');
                }
                if (!isset($files[$relative])) $add($file, $relative);
            }
            $manifest = ['format' => 1, 'database' => $database, 'started_at' => $started,
                'completed_at' => gmdate('c'), 'tables' => array_column($tables, 0),
                'attachment_records' => count($attachments), 'files' => $files,
                'scope' => 'Application database and registered attachments; excludes code, secrets, and sessions.'];
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            $zip->compressFiles(Phar::GZ);
            unset($add, $zip);
            $zip = null;
            self::verify($work . '/backup.zip');
            $db->query('UNLOCK TABLES');
            $tablesLocked = false;
            $archive = $directory . '/helpdesk-' . $id . '.zip';
            if (!rename($work . '/backup.zip', $archive)) throw new RuntimeException('Cannot publish the completed backup.');
            $result = ['success' => true, 'completed_at' => gmdate('c'), 'archive' => basename($archive),
                'bytes' => filesize($archive), 'attachment_records' => count($attachments)];
            $this->record($directory, $result);
            // Retain only this tool's completed archives, and only after a successful new backup.
            $previous = array_values(array_filter($this->completed($directory), static fn($file) => $file !== $archive));
            foreach (array_slice($previous, $keep - 1) as $old) {
                if (!unlink($old)) throw new RuntimeException('Backup succeeded, but retention cleanup failed.');
            }
            return $result;
        } catch (Throwable $error) {
            $this->record($directory, ['success' => false, 'attempted_at' => gmdate('c'),
                'error' => 'Backup failed. Check MySQL, file availability, permissions, and disk space.']);
            throw $error;
        } finally {
            unset($add, $zip);
            if ($db instanceof mysqli) {
                if ($tablesLocked) $db->query('UNLOCK TABLES');
                $db->close();
            }
            if ($work !== null) {
                foreach (['client.cnf', 'database.sql', 'stdout.log', 'stderr.log', 'backup.zip'] as $name) {
                    if (is_file($work . '/' . $name)) unlink($work . '/' . $name);
                }
                rmdir($work);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function within(string $path, string $parent): bool
    {
        $normalize = static fn($value) => strtolower(str_replace('\\', '/', rtrim($value, '/\\')));
        return $normalize($path) === $normalize($parent) || str_starts_with($normalize($path), $normalize($parent) . '/');
    }

    private function completed(string $directory): array
    {
        $archives = array_values(array_filter(glob($directory . '/helpdesk-*.zip') ?: [],
            static fn($file) => !is_link($file) && preg_match('/^helpdesk-\d{8}T\d{6}Z-[a-f0-9]{8}\.zip$/D', basename($file))));
        usort($archives, static fn($a, $b) => filemtime($b) <=> filemtime($a) ?: strcmp($b, $a));
        return $archives;
    }

    private static function write(string $file, string $text): void
    {
        if (file_put_contents($file, $text) !== strlen($text)) throw new RuntimeException('Unable to write backup metadata.');
    }

    private function record(string $directory, array $result): void
    {
        self::write($directory . '/last-attempt.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        if ($result['success']) self::write($directory . '/last-success.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    private static function execute(array $command, string $work): void
    {
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $work . '/stdout.log', 'w'],
            2 => ['file', $work . '/stderr.log', 'w']], $pipes, null, null, ['bypass_shell' => true, 'create_no_window' => true]);
        if (!is_resource($process)) throw new RuntimeException('Unable to start mysqldump.');
        fclose($pipes[0]);
        $deadline = time() + 600;
        do {
            $status = proc_get_status($process);
            if (!$status['running']) break;
            if (time() >= $deadline) {
                proc_terminate($process);
                proc_close($process);
                throw new RuntimeException('Database export timed out.');
            }
            usleep(100000);
        } while (true);
        $closed = proc_close($process);
        $code = $status['exitcode'] >= 0 ? $status['exitcode'] : $closed;
        if ($code !== 0) throw new RuntimeException('Database export failed. Check MySQL availability and export permissions.');
    }
}
