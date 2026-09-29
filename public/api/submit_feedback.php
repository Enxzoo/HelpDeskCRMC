<?php
/**
 * submit_feedback.php
 * API endpoint for students to rate resolved concerns
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/csrf.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$inquiryId = (int)($input['inquiry_id'] ?? 0);
$rating = (int)($input['rating'] ?? 0);
$comment = trim($input['comment'] ?? '');

if (!$inquiryId || !$rating || $rating < 1 || $rating > 5) {
    echo json_encode(['error' => 'Invalid inquiry ID or rating (1-5 required)']);
    exit;
}

try {
    $db = getDbConnection();

    // Verify the inquiry belongs to this student and is resolved
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

    if ((int)$inquiry['student_id'] !== (int)$_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    if ($inquiry['status'] !== 'Resolved') {
        echo json_encode(['error' => 'Can only rate resolved concerns']);
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