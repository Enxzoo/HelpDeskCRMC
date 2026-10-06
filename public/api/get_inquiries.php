<?php
/**
 * get_inquiries.php
 * API endpoint to fetch inquiries for students
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../app/config/env.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../app/models/Inquiry.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Require authenticated student session
session_start();
requireApiUser(['student']);

try {
    $studentId = (int) $_SESSION['user_id'];
    $inquiryModel = new Inquiry();

    // Get all inquiries for this student
    $inquiries = $inquiryModel->findByStudent($studentId);

    echo json_encode([
        'success' => true,
        'inquiries' => $inquiries
    ]);

} catch (Exception $e) {
    error_log('Get inquiries error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch inquiries'
    ]);
}
