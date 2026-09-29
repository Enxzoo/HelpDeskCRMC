<?php
/**
 * get_responses.php
 * API endpoint to fetch all responses for a specific inquiry
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../app/config/env.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../app/models/Inquiry.php';
require_once __DIR__ . '/../../app/helpers/csrf.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Require authenticated session
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get inquiry ID
$inquiryId = (int)($_GET['inquiry_id'] ?? 0);

if ($inquiryId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid inquiry ID']);
    exit;
}

try {
    $inquiryModel = new Inquiry();

    // Get the inquiry to check permissions
    $inquiry = $inquiryModel->findById($inquiryId);

    if (!$inquiry) {
        http_response_code(404);
        echo json_encode(['error' => 'Inquiry not found']);
        exit;
    }

    // Check if user has permission to view this inquiry
    $userId = (int)$_SESSION['user_id'];
    $userRole = $_SESSION['role'];

    // Students can only view their own inquiries
    if ($userRole === 'student' && (int)$inquiry['student_id'] !== $userId) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    // Staff can view inquiries from their office
    if ($userRole === 'staff') {
        $officeId = $_SESSION['office_id'] ?? null;
        if ($officeId && (int)$inquiry['office_id'] !== (int)$officeId) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit;
        }
    }

    // Get all responses for this inquiry
    $responses = $inquiryModel->getReplies($inquiryId);

    echo json_encode([
        'success' => true,
        'responses' => $responses,
        'inquiry' => $inquiry
    ]);

} catch (Exception $e) {
    error_log('Get responses error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch responses'
    ]);
}
