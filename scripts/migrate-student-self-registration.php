<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/config/database.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = getDbConnection();
$legacy = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_profiles' AND COLUMN_NAME = 'approval_status'")->num_rows > 0;
if ($legacy) {
    $backup = ['database' => $db->query('SELECT DATABASE() AS name')->fetch_assoc()['name'],
        'student_profiles' => $db->query('SELECT * FROM student_profiles')->fetch_all(MYSQLI_ASSOC),
        'student_account_events' => $db->query('SELECT * FROM student_account_events')->fetch_all(MYSQLI_ASSOC)];
    $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'helpdeskcrmc-account-migration-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.json';
    $handle = fopen($file, 'x');
    if (!$handle) throw new RuntimeException('Unable to back up the legacy account metadata.');
    try {
        $json = json_encode($backup, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        if (fwrite($handle, $json) !== strlen($json)) throw new RuntimeException('Incomplete metadata backup; migration cancelled.');
    } finally { fclose($handle); }
    chmod($file, 0600);
    echo "Legacy account metadata backup: $file\n";
}
$db->multi_query(file_get_contents(__DIR__ . '/../database/migrations/20261005_student_self_registration.sql'));
do {
    $result = $db->store_result();
    if ($result) $result->free();
} while ($db->more_results() && $db->next_result());
echo "Student self-registration enabled. Existing account IDs, helpdesk records, and suspension flags are unchanged.\n";
