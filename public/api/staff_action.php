<?php
/**
 * staff_action.php
 * Handles staff responses and status updates on student inquiries via AJAX.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../app/config/env.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/Inquiry.php';
require_once __DIR__ . '/../../app/helpers/csrf.php';

session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'staff' && $_SESSION['role'] !== 'admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}

if (!csrf_verify_header()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'CSRF verification failed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';
$inquiryId = (int)($input['inquiry_id'] ?? 0);
$staffId = (int)$_SESSION['user_id'];

if ($inquiryId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid inquiry ID.']);
    exit;
}

$inquiryModel = new Inquiry();

if ($action === 'add_reply') {
    $message = trim($input['message'] ?? '');
    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Reply message cannot be empty.']);
        exit;
    }

    $replyId = $inquiryModel->addReply($inquiryId, $staffId, $message);
    $updatedInquiry = $inquiryModel->findById($inquiryId);

    echo json_encode([
        'success'  => true,
        'reply_id' => $replyId,
        'inquiry'  => $updatedInquiry
    ]);
    exit;
}

if ($action === 'update_status') {
    $status = trim($input['status'] ?? '');
    if (!in_array($status, ['Pending', 'In Progress', 'Resolved'], true)) {
        echo json_encode(['success' => false, 'error' => 'Invalid status option.']);
        exit;
    }

    $success = $inquiryModel->updateStatus($inquiryId, $status);
    $updatedInquiry = $inquiryModel->findById($inquiryId);

    echo json_encode([
        'success' => $success,
        'inquiry' => $updatedInquiry
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action.']);
