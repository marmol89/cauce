<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Retry;

use Marmol89\Cauce\Contracts\RetryStrategy;

class DecorrelatedJitter implements RetryStrategy
{
    public function __construct(
        protected int $maxAttempts = 5,
        protected int $base = 1,
        protected int $cap = 300,
    ) {
    }

    public function delay(int $attempt): int
    {
        // AWS-style decorrelated jitter: sleep = min(cap, random_between(base, prev * 3))
        // For attempt #1 we use $base as the seed.
        $exp = min(30, max(0, $attempt - 1));
        $previous = $attempt <= 1 ? $this->base : ($this->base * (2 ** $exp));
        $high = max($this->base, $previous * 3);

        if ($high > PHP_INT_MAX || $high < 0) {
            return $this->cap;
        }

        return min($this->cap, random_int($this->base, max($this->base + 1, (int) $high)));
    }

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function name(): string
    {
        return 'decorrelated-jitter';
    }
}
