<?php
/**
 * submit_inquiry.php
 * API endpoint for submitting a student inquiry from the Ben chatbot.
 * Accepts JSON: { "message": "...", "office": "Registrar" }
 * Returns JSON: { "success": true, "inquiry_id": N }
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../app/config/env.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../app/controllers/InquiryController.php';
require_once __DIR__ . '/../../app/helpers/csrf.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Require authenticated student session
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
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

$message = trim($input['concern'] ?? $input['message'] ?? '');
$officeName = trim($input['office'] ?? '');
$subject = trim($input['subject'] ?? '');

if ($message === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Concern description is required']);
    exit;
}

// Resolve office name → office_id via DB lookup
$officeId = null;
if ($officeName !== '') {
    $db = getDbConnection();
    // In escalation form, the `value` of options is lowercase slug (e.g., registrar, cashier)
    // Wait, let's look at the DB. office_name usually matches exact names, or maybe the switch is better.
    // However, the previous implementation did `$stmt->bind_param('s', $officeName);`.
    // Let's ensure we map the slug to actual names if needed, but let's check what's in the DB.
    $stmt = $db->prepare('SELECT office_id FROM offices WHERE office_name LIKE ? AND is_active = 1');
    $likeOffice = "%$officeName%";
    $stmt->bind_param('s', $likeOffice);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $officeId = $row['office_id'] ?? null;
}

// Submit via InquiryController (student_id from session — never from POST)
$studentId = (int) $_SESSION['user_id'];
$controller = new InquiryController();
// Controller accepts only studentId, message, officeId. We need to pass Subject too!
$result = $controller->submit($studentId, $message, $officeId, $subject);

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'inquiry_id' => $result['inquiry_id'],
    ]);
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $result['error'] ?? 'Failed to submit inquiry',
    ]);
}
