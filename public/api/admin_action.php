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
require_once __DIR__ . '/../../app/middleware/AuthMiddleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

session_start();

requireApiUser(['admin']);

if (!csrf_verify_header()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'CSRF verification failed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request data']);
    exit;
}
foreach (['action', 'first_name', 'last_name', 'email', 'password', 'role', 'student_number'] as $field) {
    if (isset($input[$field]) && !is_string($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid request data']);
        exit;
    }
}
$action = $input['action'] ?? '';

$userModel = new User();

if ($action === 'create_user') {
    $firstName = trim($input['first_name'] ?? '');
    $lastName = trim($input['last_name'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $role = trim($input['role'] ?? 'student');
    if ($role === 'student') {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Students must create their own account and accept the Terms of Service and Privacy Policy.']);
        exit;
    }
    $officeId = !empty($input['office_id']) ? (int) filter_var($input['office_id'], FILTER_VALIDATE_INT) : null;
    $studentNum = !empty($input['student_number']) ? trim($input['student_number']) : null;

    if (
        !in_array($role, ['student', 'staff', 'admin'], true)
        || mb_strlen($firstName) > 100 || mb_strlen($lastName) > 100
        || mb_strlen($email) > 150 || mb_strlen($studentNum ?? '') > 30
    ) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid account details']);
        exit;
    }
    if ($role === 'staff') {
        $officeStmt = getDbConnection()->prepare('SELECT office_id FROM offices WHERE office_id = ? AND is_active = 1');
        $officeStmt->bind_param('i', $officeId);
        $officeStmt->execute();
        if (!$officeStmt->get_result()->fetch_assoc()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Please select an active office for this staff account.']);
            exit;
        }
    } else {
        $officeId = null;
    }

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
        $userId = $userModel->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'office_id' => $officeId,
            'student_number' => $role === 'student' ? $studentNum : null,
        ]);
        echo json_encode([
            'success' => true,
            'message' => 'User account created successfully.',
            'user_id' => $userId
        ]);
    } catch (Throwable $e) {
        error_log('User creation failed: ' . $e->getMessage());
        http_response_code($e instanceof mysqli_sql_exception && $e->getCode() === 1062 ? 409 : 500);
        echo json_encode([
            'success' => false,
            'error' => $e instanceof mysqli_sql_exception && $e->getCode() === 1062
                ? 'An account with this email or student number already exists.' : 'Failed to create user.'
        ]);
    }
    exit;
}

if ($action === 'toggle_user_status') {
    $userId = (int) filter_var($input['user_id'] ?? 0, FILTER_VALIDATE_INT);
    $target = $userId > 0 ? $userModel->findById($userId) : null;
    if ($target && $target['role'] === 'student') {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Use Student Accounts to change access with a recorded reason.']);
        exit;
    }
    if ($userId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid User ID.']);
        exit;
    }

    if ($userId === (int) $_SESSION['user_id']) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'You cannot deactivate your own account.']);
        exit;
    }
    try {
        $success = $userModel->toggleStatus($userId);
        http_response_code($success ? 200 : 404);
        echo json_encode(['success' => $success]);
    } catch (Throwable $e) {
        error_log('User status change failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update user status.']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action.']);
