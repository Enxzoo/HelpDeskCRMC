<?php
/**
 * AuthController.php
 * Handles login, logout, and the "which dashboard does this role go to"
 * decision. If a bug is "wrong person can access something" or
 * "login isn't working", start here.
 */

require_once __DIR__ . '/../models/User.php';

class AuthController
{
    /**
     * Attempts to log a user in. Returns an array like:
     *   ['success' => true, 'redirect' => '...']
     *   ['success' => false, 'error' => '...']
     */
    public function login(string $email, string $password): array
    {
        error_log("[AuthController::login] attempt for email: {$email}");

        $userModel = new User();
        $user = $userModel->findByEmail($email);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            error_log("[AuthController::login] failed for email: {$email}");
            return ['success' => false, 'error' => 'Invalid email or password.'];
        }

        // Store only what's needed in the session — never the password hash.
        $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['name'] ?? 'User');

        // Prevent session-fixation: issue a new session ID on successful login.
        session_regenerate_id(true);

        $_SESSION['user_id'] = (int) ($user['user_id'] ?? $user['id']);
        $_SESSION['name']    = $fullName;
        $_SESSION['role']    = $user['role'];

        error_log("[AuthController::login] success for user_id: {$_SESSION['user_id']} role: {$user['role']}");

        return ['success' => true, 'redirect' => $this->dashboardFor($user['role'])];
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