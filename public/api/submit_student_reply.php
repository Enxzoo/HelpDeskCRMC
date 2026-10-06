<?php
/**
 * submit_student_reply.php
 * API endpoint for students to reply to their inquiries
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/csrf.php';
require_once __DIR__ . '/../../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../app/models/Inquiry.php';

session_start();

requireApiUser(['student']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!csrf_verify_header()) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || !is_string($input['message'] ?? null)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid reply data']);
    exit;
}
$inquiryId = (int) filter_var($input['inquiry_id'] ?? 0, FILTER_VALIDATE_INT);
$message = trim($input['message'] ?? '');

if (!$inquiryId || !$message) {
    echo json_encode(['error' => 'Missing inquiry ID or message']);
    exit;
}

try {
    $db = getDbConnection();

    // Verify the inquiry belongs to this student and is awaiting a student response.
    $verifyStmt = $db->prepare("SELECT student_id, status FROM inquiries WHERE inquiry_id = ?");
    $verifyStmt->bind_param('i', $inquiryId);
    $verifyStmt->execute();
    $result = $verifyStmt->get_result();
    $inquiry = $result->fetch_assoc();

    if (!$inquiry || (int) $inquiry['student_id'] !== (int) $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    if ($inquiry['status'] !== 'On Hold') {
        http_response_code(409);
        echo json_encode(['error' => 'Replies are only available while the concern is On Hold']);
        exit;
    }

    $responseId = (new Inquiry())->addResponse($inquiryId, (int) $_SESSION['user_id'], $message);
    echo json_encode(['success' => true, 'response_id' => $responseId, 'message' => 'Reply submitted successfully']);
} catch (ResponseNotAllowedException $e) {
    http_response_code(409);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Student reply error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to submit reply']);
}
