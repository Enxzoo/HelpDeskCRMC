<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/config/database.php';
$db = getDbConnection();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db->multi_query(file_get_contents(__DIR__ . '/../database/migrations/20261003_add_student_profiles.sql'));
do {
    $result = $db->store_result();
    if ($result) $result->free();
} while ($db->more_results() && $db->next_result());
require __DIR__ . '/migrate-student-self-registration.php';
echo "Student profile migration applied. Students can sign in without account approval.\n";
