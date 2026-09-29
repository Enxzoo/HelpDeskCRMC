<?php
/**
 * debug_inquiries.php
 * Debug endpoint to check inquiry data
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../app/config/env.php';
require_once __DIR__ . '/../../app/config/database.php';

try {
    $db = getDbConnection();

    // Get all inquiries with student names
    $query = "SELECT i.*,
                     CONCAT(u.first_name, ' ', u.last_name) as student_name,
                     u.email as student_email,
                     o.office_name
              FROM inquiries i
              LEFT JOIN users u ON i.student_id = u.user_id
              LEFT JOIN offices o ON i.office_id = o.office_id
              ORDER BY i.created_at DESC";

    $result = $db->query($query);

    if (!$result) {
        throw new Exception('Query failed: ' . $db->error);
    }

    $inquiries = [];
    while ($row = $result->fetch_assoc()) {
        $inquiries[] = $row;
    }

    echo json_encode([
        'success' => true,
        'count' => count($inquiries),
        'inquiries' => $inquiries
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
