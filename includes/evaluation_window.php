<?php
declare(strict_types=1);

/**
 * RMF-042: single source of truth for whether a semester's evaluation
 * window is upcoming, open, or closed. Student, Faculty, and Admin
 * pages all call this instead of each writing their own date logic.
 */

const WINDOW_UPCOMING = 'UPCOMING';
const WINDOW_OPEN = 'OPEN';
const WINDOW_CLOSED = 'CLOSED';

/**
 * $openAt / $closeAt: 'Y-m-d H:i:s' strings (as stored in MySQL).
 * $now: optional override, same format, used only for testing.
 */
function get_window_status(string $openAt, string $closeAt, ?string $now = null): string
{
    $nowDt = $now !== null ? new DateTimeImmutable($now) : new DateTimeImmutable();
    $openDt = new DateTimeImmutable($openAt);
    $closeDt = new DateTimeImmutable($closeAt);

    if ($nowDt < $openDt) {
        return WINDOW_UPCOMING;
    }
    if ($nowDt > $closeDt) {
        return WINDOW_CLOSED;
    }
    return WINDOW_OPEN;
}

/**
 * Small helper for displaying a status as a readable label.
 * Kept separate from get_window_status() so pages can still branch
 * on the raw constant for logic, and use this only for display text.
 */
function window_status_label(string $status): string
{
    return match ($status) {
        WINDOW_UPCOMING => 'Upcoming',
        WINDOW_OPEN => 'Open',
        WINDOW_CLOSED => 'Closed',
        default => $status,
    };
}