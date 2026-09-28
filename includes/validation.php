<?php
declare(strict_types=1);


function e($value): string
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function validate_required_string(?string $value, int $maxLength): ?string
{
    $value = trim((string) ($value ?? ''));
    if ($value === '' || mb_strlen($value) > $maxLength) {
        return null;
    }
    return $value;
}

function validate_optional_text(?string $value, int $maxLength): ?string
{
    $value = trim((string) ($value ?? ''));
    if (mb_strlen($value) > $maxLength) {
        return null;
    }
    return $value;
}

function validate_positive_int($value): ?int
{
    $filtered = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    return $filtered === false ? null : $filtered;
}

function validate_rating($value): ?int
{
    $filtered = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 5],
    ]);
    return $filtered === false ? null : $filtered;
}