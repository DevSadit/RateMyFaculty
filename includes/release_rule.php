<?php
declare(strict_types=1);

const RELEASE_THRESHOLD = 3;

function is_result_released(string $windowStatus, int $responseCount): bool
{
    return $windowStatus === WINDOW_CLOSED && $responseCount >= RELEASE_THRESHOLD;
}