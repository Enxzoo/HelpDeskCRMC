<?php
/**
 * admin_action.php
 * Handles user management and system settings for admins via AJAX.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../app/config/env.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/User.php';
require_once __DIR__ . '/../../app/helpers/csrf.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access. Admin privileges required.']);
    exit;
}

if (!csrf_verify_header()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'CSRF verification failed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

$userModel = new User();

if ($action === 'create_user') {
    $firstName = trim($input['first_name'] ?? '');
    $lastName  = trim($input['last_name'] ?? '');
    $email     = trim($input['email'] ?? '');
    $password  = $input['password'] ?? '';
    $role      = trim($input['role'] ?? 'student');
    $officeId  = !empty($input['office_id']) ? (int)$input['office_id'] : null;
    $studentNum= !empty($input['student_number']) ? trim($input['student_number']) : null;

    if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'All required fields must be filled out.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid email address format.']);
        exit;
    }

    if ($userModel->findByEmail($email)) {
        echo json_encode(['success' => false, 'error' => 'An account with this email already exists.']);
        exit;
    }

    try {
        $userId = $userModel->create($firstName, $lastName, $email, $password, $role, $officeId, $studentNum);
        echo json_encode([
            'success' => true,
            'message' => 'User account created successfully.',
            'user_id' => $userId
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to create user: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'toggle_user_status') {
    $userId = (int)($input['user_id'] ?? 0);
    if ($userId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid User ID.']);
        exit;
    }

    $success = $userModel->toggleStatus($userId);
    echo json_encode(['success' => $success]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action.']);
