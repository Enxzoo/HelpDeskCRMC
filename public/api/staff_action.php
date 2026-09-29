<?php
/**
 * staff_action.php
 * API endpoint for staff to respond to inquiries and update status
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../app/config/env.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../app/models/Inquiry.php';
require_once __DIR__ . '/../../app/helpers/csrf.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Require authenticated staff/admin session
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['staff', 'admin'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Verify CSRF token from header
if (!csrf_verify_header()) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);

$action = $input['action'] ?? '';
$inquiryId = (int)($input['inquiry_id'] ?? 0);
$message = trim($input['message'] ?? '');
$status = trim($input['status'] ?? '');

if ($inquiryId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid inquiry ID']);
    exit;
}

$inquiryModel = new Inquiry();
$staffId = (int)$_SESSION['user_id'];

try {
    switch ($action) {
        case 'respond':
            // Add staff response and optionally update status
            if ($message === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Message cannot be empty']);
                exit;
            }

            // Add the response
            $responseId = $inquiryModel->addResponse($inquiryId, $staffId, $message);

            // Update status if provided
            if ($status !== '' && in_array($status, ['Pending', 'In Progress', 'Resolved'])) {
                $inquiryModel->updateStatus($inquiryId, $status);
            }

            echo json_encode([
                'success' => true,
                'response_id' => $responseId,
                'message' => 'Response added successfully'
            ]);
            break;

        case 'update_status':
            // Update inquiry status only
            if ($status === '' || !in_array($status, ['Pending', 'In Progress', 'Resolved'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid status']);
                exit;
            }

            $inquiryModel->updateStatus($inquiryId, $status);

            echo json_encode([
                'success' => true,
                'message' => 'Status updated successfully'
            ]);
            break;

        case 'assign':
            // Assign inquiry to staff member
            $inquiryModel->assignStaff($inquiryId, $staffId);

            echo json_encode([
                'success' => true,
                'message' => 'Inquiry assigned successfully'
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    error_log('Staff action error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to process request'
    ]);
}
