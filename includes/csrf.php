<?php
declare(strict_types=1);

/**
 * RMF-024: CSRF (Cross-Site Request Forgery) protection.
 * Every form-rendering page calls csrf_field() inside its <form>.
 * Every POST handler calls verify_csrf_token() FIRST, before reading
 * any other $_POST value.
 */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf_token(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';

    // hash_equals() is timing-safe — a plain === comparison leaks timing
    // information that could theoretically help an attacker guess the
    // token character-by-character.
    if ($expected === '' || !hash_equals($expected, $submitted)) {
        http_response_code(403);
        die('Your session could not be verified. Please go back and try again.');
    }
}