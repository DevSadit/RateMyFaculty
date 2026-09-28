<?php
declare(strict_types=1);

/**
 * RMF-025: shared output-escaping and input-validation helpers.
 * Every page that renders user-supplied text or accepts form input
 * should use these instead of writing its own checks.
 */

/**
 * Safely escape a value for HTML output. Always use this when printing
 * anything that came from the database or from user input (names,
 * course titles, comments, error messages, etc.).
 */
function e($value): string
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * A required text field: trims whitespace, rejects empty or
 * over-length values. Returns the cleaned string, or null if invalid.
 */
function validate_required_string(?string $value, int $maxLength): ?string
{
    $value = trim((string) ($value ?? ''));
    if ($value === '' || mb_strlen($value) > $maxLength) {
        return null;
    }
    return $value;
}

/**
 * An optional text field (e.g. an evaluation comment): empty is fine,
 * but if provided it must not exceed maxLength. Returns the cleaned
 * string (possibly ''), or null if it's too long.
 */
function validate_optional_text(?string $value, int $maxLength): ?string
{
    $value = trim((string) ($value ?? ''));
    if (mb_strlen($value) > $maxLength) {
        return null;
    }
    return $value;
}

/**
 * A positive whole number (e.g. an ID from a form/query string).
 * Returns the integer, or null if invalid.
 */
function validate_positive_int($value): ?int
{
    $filtered = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    return $filtered === false ? null : $filtered;
}

/**
 * A 1–5 evaluation rating. Returns the integer, or null if invalid.
 */
function validate_rating($value): ?int
{
    $filtered = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 5],
    ]);
    return $filtered === false ? null : $filtered;
}