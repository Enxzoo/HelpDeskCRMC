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
require_once __DIR__ . '/../../app/models/User.php';
require_once __DIR__ . '/../../app/helpers/csrf.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Require authenticated staff/admin session
session_start();
$currentUser = requireApiUser(['staff', 'admin']);

// Verify CSRF token from header
if (!csrf_verify_header()) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request data']);
    exit;
}
foreach (['action', 'message', 'status', 'priority'] as $field) {
    if (isset($input[$field]) && !is_string($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid request data']);
        exit;
    }
}

$action = $input['action'] ?? '';
$inquiryId = (int) filter_var($input['inquiry_id'] ?? 0, FILTER_VALIDATE_INT);
$message = trim($input['message'] ?? '');
$status = trim($input['status'] ?? '');
$priority = isset($input['priority']) ? trim((string) $input['priority']) : '';

if ($inquiryId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid inquiry ID']);
    exit;
}

$staffId = (int) $_SESSION['user_id'];

try {
    $inquiryModel = new Inquiry();
    $inquiry = $inquiryModel->findById($inquiryId);
    if (!$inquiry) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Concern not found']);
        exit;
    }
    if (
        $currentUser['role'] === 'staff'
        && (empty($currentUser['office_id']) || (int) $inquiry['office_id'] !== (int) $currentUser['office_id'])
    ) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You can only manage concerns assigned to your office.']);
        exit;
    }
    if ($status !== '' && !in_array($status, Inquiry::STATUSES, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid status']);
        exit;
    }
    switch ($action) {
        case 'respond':
            // Add staff response and optionally update status
            if ($message === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Message cannot be empty']);
                exit;
            }

            // Add the response
            $responseId = $inquiryModel->addResponse($inquiryId, $staffId, $message, $status !== '' ? $status : null);

            echo json_encode([
                'success' => true,
                'response_id' => $responseId,
                'message' => 'Response added successfully'
            ]);
            break;

        case 'update_status':
            // Update inquiry status only
            if ($status === '') {
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

        case 'override_priority':
            $allowedPriorities = ['Critical/Urgent', 'High', 'Normal', 'Low'];
            if ($priority !== '' && !in_array($priority, $allowedPriorities, true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid urgency level']);
                exit;
            }

            $override = $priority === '' ? null : $priority;
            $inquiryModel->overridePriority($inquiryId, $override, $staffId);

            echo json_encode([
                'success' => true,
                'priority' => $override ?? $inquiry['ai_priority'] ?? 'Needs triage',
                'priority_override' => $override,
                'priority_override_by' => $override === null ? null : $staffId,
                'priority_override_staff_name' => $override === null ? null : (string) ($_SESSION['name'] ?? ''),
                'message' => 'Urgency updated successfully'
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (ResponseNotAllowedException $e) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Staff action error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to process request'
    ]);
}
