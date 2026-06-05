<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Contracts;

interface RetryStrategy
{
    /**
     * Compute the delay (in seconds) before the next attempt.
     */
    public function delay(int $attempt): int;

    /**
     * Maximum number of attempts before giving up.
     */
    public function maxAttempts(): int;

    /**
     * Human-friendly name for the strategy.
     */
    public function name(): string;
}
