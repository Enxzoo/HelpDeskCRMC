<?php
/**
 * AuthController.php
 * Handles login, logout, and the "which dashboard does this role go to"
 * decision. If a bug is "wrong person can access something" or
 * "login isn't working", start here.
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/StudentProfile.php';

class AuthController
{
    /**
     * Attempts to log a user in. Returns an array like:
     *   ['success' => true, 'redirect' => '...']
     *   ['success' => false, 'error' => '...']
     */
    public function login(string $email, string $password): array
    {
        $userModel = new User();
        $user = $userModel->findByEmail($email);

        if (!$user || !(int)$user['is_active'] || !empty($user['deleted_at']) || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Invalid email or password.'];
        }

        // Store only what's needed in the session — never the password hash.
        $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['name'] ?? 'User');

        // Prevent session-fixation: issue a new session ID on successful login.
        session_regenerate_id(true);
        $userModel->updateLastLogin((int)$user['user_id']);

        $_SESSION['user_id'] = (int) ($user['user_id'] ?? $user['id']);
        $_SESSION['name']    = $fullName;
        $_SESSION['role']    = $user['role'];
        $_SESSION['office_id'] = $user['role'] === 'staff' && !empty($user['office_id'])
            ? (int)$user['office_id']
            : null;

        error_log("[AuthController::login] success for user_id: {$_SESSION['user_id']} role: {$user['role']}");

        return ['success' => true, 'redirect' => $this->dashboardFor($user['role'])];
    }

    /** Creates a student account and signs the student in immediately. */
    public function registerStudent(array $data): array
    {
        try {
            $userId = (new StudentProfile())->register($data);
            $user = (new User())->findById($userId);
            session_regenerate_id(true);
            (new User())->updateLastLogin($userId);
            $_SESSION['user_id'] = $userId;
            $_SESSION['name'] = trim($user['first_name'] . ' ' . $user['last_name']);
            $_SESSION['role'] = 'student';
            $_SESSION['office_id'] = null;
            return ['success' => true, 'redirect' => 'dashboard_student.php'];
        } catch (StudentAccountException $error) {
            return ['success' => false, 'error' => $error->getMessage()];
        } catch (Throwable $error) {
            error_log('Student registration failed: ' . $error->getMessage());

            if ($error instanceof mysqli_sql_exception && (int)$error->getCode() === 1062) {
                return ['success' => false, 'error' => 'An account with these details already exists. Sign in or contact the registrar for help.'];
            }

            return ['success' => false, 'error' => 'Account creation is temporarily unavailable. Please try again shortly.'];
        }
    }

    
    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public function dashboardFor(string $role): string
    {
        return match ($role) {
            'admin' => 'dashboard_admin.php',
            'staff' => 'dashboard_staff.php',
            default => 'dashboard_student.php',
        };
    }
}
