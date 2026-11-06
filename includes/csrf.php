<?php
/**
 * Solvex Report — CSRF Protection
 * 
 * Token-based CSRF protection.
 * - csrf_field() outputs a hidden input with the token
 * - csrf_verify() checks the token on POST requests
 */

/**
 * Generate or retrieve the current CSRF token.
 */
function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Output a hidden input field with the CSRF token.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verify the CSRF token on a POST request.
 * Dies with 403 if invalid.
 */
function csrf_verify(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $token = $_POST['csrf_token'] ?? '';

    if (
        empty($token) ||
        !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        http_response_code(403);
        die('Invalid or missing security token. Please go back and try again.');
    }

    // Keep token valid across multiple tabs and AJAX operations in the session
}

/**
 * Verify CSRF for JSON/AJAX POSTs without rotating the session token.
 * Rotation would invalidate subsequent in-page fetch calls (affected toggle, mark-read).
 */
function csrf_verify_ajax(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }

    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    return $token !== ''
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}
