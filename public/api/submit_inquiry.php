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
requireApiUser(['student']);

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
    echo json_encode(['success' => false, 'error' => 'Invalid submission data']);
    exit;
}

$messageValue = $input['concern'] ?? $input['message'] ?? '';
$officeValue = $input['office'] ?? '';
$subjectValue = $input['subject'] ?? '';
$confirmedDuplicateValue = $input['confirmed_duplicate_of'] ?? null;
if (
    !is_string($messageValue)
    || !is_string($officeValue)
    || !is_string($subjectValue)
    || ($confirmedDuplicateValue !== null
        && !is_int($confirmedDuplicateValue)
        && !(is_string($confirmedDuplicateValue) && ctype_digit($confirmedDuplicateValue)))
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid submission data']);
    exit;
}

$confirmedDuplicateOf = $confirmedDuplicateValue === null
    ? null
    : (int) $confirmedDuplicateValue;
if ($confirmedDuplicateOf !== null && $confirmedDuplicateOf <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid submission data']);
    exit;
}

$message = trim($messageValue);
$officeSlug = strtolower(trim($officeValue));
$subject = trim($subjectValue);
if (mb_strlen($subject) > 150 || mb_strlen($message) > 10000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Use a subject of 150 characters or fewer and a description of 10000 characters or fewer.']);
    exit;
}

if ($message === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Concern description is required']);
    exit;
}

// Resolve only form-provided office slugs to known active office records.
$officeOptions = [
    'registrar' => ['Registrar'],
    'cashier' => ['Finance', 'Cashier'],
    'guidance' => ['Guidance', 'Guidance Office'],
    'saso' => ['SASO'],
    'cte' => ['CTE'],
    'cbe' => ['CBE'],
    'ccs' => ['CCS'],
    'cje' => ['CCJE'],
    'psychology' => ['Psychology Department'],
    'main' => ['Main Office'],
];

if (!isset($officeOptions[$officeSlug])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please select a valid office']);
    exit;
}

try {
    $db = getDbConnection();
    $officeId = null;
    foreach ($officeOptions[$officeSlug] as $officeName) {
        $stmt = $db->prepare('SELECT office_id FROM offices WHERE office_name = ? AND is_active = 1 LIMIT 1');
        $stmt->bind_param('s', $officeName);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            $officeId = (int) $row['office_id'];
            break;
        }
    }

    if ($officeId === null) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'The selected office is not available. Please choose another office or contact support.',
        ]);
        exit;
    }

    // Submit via InquiryController (student_id comes from the authenticated session).
    $studentId = (int) $_SESSION['user_id'];
    $controller = new InquiryController();
    $result = $controller->submit(
        $studentId,
        $message,
        $officeId,
        $subject,
        $confirmedDuplicateOf
    );

    if (!$result['success']) {
        if (!empty($result['duplicate_limit_reached'])) {
            http_response_code(429);
        } elseif (!empty($result['duplicate_warning'])) {
            http_response_code(409);
        } else {
            http_response_code(400);
        }
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Failed to submit inquiry',
            'duplicate_warning' => !empty($result['duplicate_warning']),
            'duplicate_limit_reached' => !empty($result['duplicate_limit_reached']),
            'matched_concern' => $result['matched_concern'] ?? null,
            'duplicate_root_id' => $result['duplicate_root_id'] ?? null,
            'submissions_in_window' => $result['submissions_in_window'] ?? null,
            'submission_limit' => $result['submission_limit'] ?? null,
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'inquiry_id' => $result['inquiry_id'],
        'urgency_priority' => $result['urgency_priority'],
        'urgency_review_required' => $result['urgency_review_required'] ?? false,
        'urgency_source' => $result['urgency_source'] ?? 'ai',
        'duplicate_of_inquiry_id' => $result['duplicate_of_inquiry_id'] ?? null,
    ]);
} catch (Throwable $exception) {
    error_log('Inquiry submission failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'We could not submit your concern. Please check My Concerns before trying again or contact support.',
    ]);
}
