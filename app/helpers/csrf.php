<?php
/**
 * csrf.php
 * Simple CSRF-token helper.
 * Generates one token per session, embeds it in forms (hidden field)
 * or AJAX requests (X-CSRF-Token header), and validates on POST.
 */

/**
 * Return (and lazily generate) the CSRF token for this session.
 */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Return a hidden <input> embedding the token — drop this inside any <form>.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Validate the token from a regular form POST ($_POST['_csrf_token']).
 */
function csrf_verify(): bool
{
    $token = $_POST['_csrf_token'] ?? '';
    return $token !== '' && hash_equals($_SESSION['_csrf_token'] ?? '', $token);
}

/**
 * Validate the token from an AJAX request (X-CSRF-Token header).
 * Works for JSON API endpoints that don't use $_POST.
 */
function csrf_verify_header(): bool
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return $token !== '' && hash_equals($_SESSION['_csrf_token'] ?? '', $token);
}
