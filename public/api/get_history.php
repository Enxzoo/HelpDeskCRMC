<?php
/**
 * get_history.php
 * API endpoint to fetch resolved inquiries for staff dashboard history
 */

require_once __DIR__ . '/../../app/config/env.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../app/models/Inquiry.php';
require_once __DIR__ . '/../../app/helpers/csrf.php';

header('Content-Type: application/json');
session_start();
requireLogin();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'staff' && $_SESSION['role'] !== 'admin')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $staffId = (int) $_SESSION['user_id'];
    $officeId = $_SESSION['office_id'] ?? null;

    $inquiryModel = new Inquiry();

    // Get resolved inquiries for this office
    $resolvedInquiries = $inquiryModel->findByOffice($officeId, 'Resolved');

    // Add resolved_by information and format dates
    foreach ($resolvedInquiries as &$inquiry) {
        // Get the last staff response to determine who resolved it
        $db = getDbConnection();
        $stmt = $db->prepare(
            'SELECT CONCAT(u.first_name, " ", u.last_name) as staff_name, ir.created_at as resolved_at
             FROM inquiry_responses ir
             LEFT JOIN users u ON ir.sender_id = u.user_id
             WHERE ir.inquiry_id = ? AND ir.sender_role = "staff"
             ORDER BY ir.created_at DESC
             LIMIT 1'
        );
        $stmt->bind_param('i', $inquiry['inquiry_id']);
        $stmt->execute();
        $lastResponse = $stmt->get_result()->fetch_assoc();

        $inquiry['resolved_by'] = $lastResponse['staff_name'] ?? 'System';
        $inquiry['resolved_at'] = $lastResponse['resolved_at'] ?? $inquiry['updated_at'];
    }

    echo json_encode([
        'success' => true,
        'history' => $resolvedInquiries
    ]);

} catch (Exception $e) {
    error_log('Error fetching history: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch history']);
}
?>