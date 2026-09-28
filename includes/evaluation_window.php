<?php
declare(strict_types=1);


const WINDOW_UPCOMING = 'UPCOMING';
const WINDOW_OPEN = 'OPEN';
const WINDOW_CLOSED = 'CLOSED';

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

function window_status_label(string $status): string
{
    return match ($status) {
        WINDOW_UPCOMING => 'Upcoming',
        WINDOW_OPEN => 'Open',
        WINDOW_CLOSED => 'Closed',
        default => $status,
    };
}