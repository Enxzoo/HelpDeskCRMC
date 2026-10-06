<?php
/**
 * submit_feedback.php
 * API endpoint for students to rate concerns that are not On Hold
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/csrf.php';
require_once __DIR__ . '/../../app/middleware/AuthMiddleware.php';

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
if (!is_array($input) || (isset($input['comment']) && !is_string($input['comment']))) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid feedback data']);
    exit;
}
$inquiryId = (int) filter_var($input['inquiry_id'] ?? 0, FILTER_VALIDATE_INT);
$rating = (int) filter_var($input['rating'] ?? 0, FILTER_VALIDATE_INT);
$comment = trim($input['comment'] ?? '');
if (mb_strlen($comment) > 500) {
    http_response_code(400);
    echo json_encode(['error' => 'Feedback comments must be 500 characters or fewer.']);
    exit;
}

if (!$inquiryId || !$rating || $rating < 1 || $rating > 5) {
    echo json_encode(['error' => 'Invalid inquiry ID or rating (1-5 required)']);
    exit;
}

try {
    $db = getDbConnection();

    // Verify the inquiry belongs to this student
    $verifyStmt = $db->prepare("
        SELECT student_id, status
        FROM inquiries
        WHERE inquiry_id = ?
    ");
    $verifyStmt->bind_param('i', $inquiryId);
    $verifyStmt->execute();
    $result = $verifyStmt->get_result();
    $inquiry = $result->fetch_assoc();

    if (!$inquiry) {
        http_response_code(404);
        echo json_encode(['error' => 'Inquiry not found']);
        exit;
    }

    if ((int) $inquiry['student_id'] !== (int) $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    if ($inquiry['status'] === 'On Hold') {
        http_response_code(409);
        echo json_encode(['error' => 'Feedback is unavailable while a response is requested']);
        exit;
    }

    // Check if feedback already exists
    $checkStmt = $db->prepare("SELECT feedback_id FROM feedback WHERE inquiry_id = ?");
    $checkStmt->bind_param('i', $inquiryId);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();

    if ($existing) {
        // Update existing feedback
        $updateStmt = $db->prepare("
            UPDATE feedback
            SET rating = ?, comment = ?, created_at = NOW()
            WHERE inquiry_id = ?
        ");
        $updateStmt->bind_param('isi', $rating, $comment, $inquiryId);
        $success = $updateStmt->execute();
    } else {
        // Insert new feedback
        $insertStmt = $db->prepare("
            INSERT INTO feedback (inquiry_id, student_id, rating, comment, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $insertStmt->bind_param('iiis', $inquiryId, $_SESSION['user_id'], $rating, $comment);
        $success = $insertStmt->execute();
    }

    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Feedback submitted successfully']);
    } else {
        throw new Exception('Failed to save feedback');
    }

} catch (Exception $e) {
    error_log('Feedback error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to submit feedback']);
}
