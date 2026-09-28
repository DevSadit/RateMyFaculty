<?php
declare(strict_types=1);

/**
 * RMF-060: single source of truth for whether an offering's results
 * are released to Faculty/Admin. Both roles call this same function —
 * there is deliberately no Admin bypass anywhere in the codebase.
 */
const RELEASE_THRESHOLD = 3;

function is_result_released(string $windowStatus, int $responseCount): bool
{
    return $windowStatus === WINDOW_CLOSED && $responseCount >= RELEASE_THRESHOLD;
}