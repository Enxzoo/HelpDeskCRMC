<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/config/database.php';
$db = getDbConnection();
$db->multi_query(file_get_contents(__DIR__ . '/../database/migrations/20261003_add_admin_workspace.sql'));
do {
    $result = $db->store_result();
    if ($result) $result->free();
} while ($db->more_results() && $db->next_result());
echo "Admin workspace migration applied.\n";
