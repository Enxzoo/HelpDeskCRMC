<?php
/**
 * AuthMiddleware.php
 * Call requireLogin() at the top of any page that needs a logged-in
 * user, or requireRole('staff') for pages restricted to one role.
 * Keeping this check in one place means every protected page behaves
 * identically — if one page's protection is buggy, they all are,
 * which makes it easy to spot.
 */

require_once __DIR__ . '/../models/User.php';

function authenticatedUser(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $user = (new User())->findById((int)$_SESSION['user_id']);
    if (!$user || !(int)$user['is_active'] || !empty($user['deleted_at'])) {
        $_SESSION = [];
        return null;
    }
    $_SESSION['role'] = $user['role'];
    $_SESSION['name'] = trim($user['first_name'] . ' ' . $user['last_name']);
    $_SESSION['office_id'] = $user['office_id'] === null ? null : (int)$user['office_id'];
    return $user;
}

function requireApiUser(array $roles = []): array
{
    try {
        $user = authenticatedUser();
    } catch (Throwable $error) {
        error_log('Authentication lookup failed: ' . $error->getMessage());
        http_response_code(503);
        echo json_encode(['success' => false, 'error' => 'Service temporarily unavailable.']);
        exit;
    }
    if ($user === null || ($roles !== [] && !in_array($user['role'], $roles, true))) {
        http_response_code($user === null ? 401 : 403);
        echo json_encode(['success' => false, 'error' => $user === null ? 'Unauthorized' : 'Access denied']);
        exit;
    }
    return $user;
}

function requireLogin(): void
{
    if (authenticatedUser() === null) {
        header('Location: login.php');
        exit;
    }
}

function requireRole(string $role): void
{
    requireLogin();

    if ($_SESSION['role'] !== $role) {
        header('Location: login.php');
        exit;
    }
}
