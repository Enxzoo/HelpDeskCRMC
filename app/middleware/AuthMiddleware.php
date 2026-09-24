<?php
/**
 * AuthMiddleware.php
 * Call requireLogin() at the top of any page that needs a logged-in
 * user, or requireRole('staff') for pages restricted to one role.
 * Keeping this check in one place means every protected page behaves
 * identically — if one page's protection is buggy, they all are,
 * which makes it easy to spot.
 */

function requireLogin(): void
{
    if (empty($_SESSION['user_id'])) {
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