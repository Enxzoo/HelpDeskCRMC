<?php
/**
 * submit_student_reply.php
 * API endpoint for students to reply to their inquiries
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
$message = trim($input['message'] ?? '');

if (!$inquiryId || !$message) {
    echo json_encode(['error' => 'Missing inquiry ID or message']);
    exit;
}

try {
    $db = getDbConnection();

    // Verify the inquiry belongs to this student
    $verifyStmt = $db->prepare("SELECT student_id FROM inquiries WHERE inquiry_id = ?");
    $verifyStmt->bind_param('i', $inquiryId);
    $verifyStmt->execute();
    $result = $verifyStmt->get_result();
    $inquiry = $result->fetch_assoc();

    if (!$inquiry || (int)$inquiry['student_id'] !== (int)$_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    // Insert student reply
    $insertStmt = $db->prepare("
        INSERT INTO inquiry_responses (inquiry_id, staff_id, message, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $insertStmt->bind_param('iis', $inquiryId, $_SESSION['user_id'], $message);

    if ($insertStmt->execute()) {
        // Update inquiry status to 'In Progress' if it was resolved
        $updateStmt = $db->prepare("
            UPDATE inquiries
            SET status = 'In Progress', updated_at = NOW()
            WHERE inquiry_id = ? AND status = 'Resolved'
        ");
        $updateStmt->bind_param('i', $inquiryId);
        $updateStmt->execute();

        echo json_encode(['success' => true, 'message' => 'Reply submitted successfully']);
    } else {
        throw new Exception('Failed to insert reply');
    }

} catch (Exception $e) {
    error_log('Student reply error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to submit reply']);
}