<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Retry;

use Marmol89\Cauce\Contracts\RetryStrategy;

class ExponentialBackoff implements RetryStrategy
{
    public function __construct(
        protected int $maxAttempts = 5,
        protected int $base = 2,
        protected int $cap = 300,
    ) {
    }

    public function delay(int $attempt): int
    {
        $expo = (int) ($this->base ** max(0, $attempt - 1));

        return min($this->cap, $expo);
    }

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function name(): string
    {
        return 'exponential';
    }
}
