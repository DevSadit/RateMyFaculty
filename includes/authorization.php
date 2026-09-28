<?php
declare(strict_types=1);

/**
 * RMF-021: reusable page guards. Every protected page starts with
 * require_login() or require_role(...) — never a hand-rolled
 * "if not logged in" check, so the rule is enforced consistently.
 */

/**
 * Ensures someone is logged in. Redirects to login.php otherwise.
 * Returns the current user's session info on success.
 */
function require_login(): array
{
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
    return current_user();
}

/**
 * Ensures someone is logged in AND holds one of the allowed roles.
 * Accepts a single role string or an array of roles.
 * Returns the current user's session info on success.
 */
function require_role($allowedRoles): array
{
    $user = require_login();
    $allowedRoles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];

    if (!in_array($user['role'], $allowedRoles, true)) {
        deny_access();
    }

    return $user;
}

/**
 * Renders a 403 page and stops execution.
 */
function deny_access(): void
{
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>403 Access Denied</title>';
    echo '<link rel="stylesheet" href="' . e(BASE_URL) . '/assets/css/style.css">';
    echo '<script src="' . e(BASE_URL) . '/assets/js/app.js" defer></script></head><body class="standalone-error"><!-- UI REDESIGN: Shared light access-denied panel. --><main class="panel error-card"><p class="eyebrow">RateMyFaculty / Access</p>';
    echo '<h1>403 — Access Denied</h1>';
    echo '<p>You do not have permission to view this page.</p>';
    echo '<p><a class="button" href="' . e(BASE_URL) . '/login.php">Return to login</a></p>';
    echo '</main></body></html>';
    exit;
}