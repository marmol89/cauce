<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Retry;

use Marmol89\Cauce\Contracts\RetryStrategy;

class LinearBackoff implements RetryStrategy
{
    public function __construct(
        protected int $maxAttempts = 5,
        protected int $base = 5,
        protected int $cap = 300,
    ) {
    }

    public function delay(int $attempt): int
    {
        return min($this->cap, $this->base * max(1, $attempt));
    }

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function name(): string
    {
        return 'linear';
    }
}
