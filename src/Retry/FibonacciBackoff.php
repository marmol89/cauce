<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Retry;

use Marmol89\Cauce\Contracts\RetryStrategy;

class FibonacciBackoff implements RetryStrategy
{
    public function __construct(
        protected int $maxAttempts = 5,
        protected int $base = 1,
        protected int $cap = 300,
    ) {
    }

    public function delay(int $attempt): int
    {
        return min($this->cap, $this->base * $this->fibonacci(max(1, $attempt)));
    }

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function name(): string
    {
        return 'fibonacci';
    }

    protected function fibonacci(int $n): int
    {
        if ($n <= 1) {
            return 1;
        }

        $a = 0;
        $b = 1;
        for ($i = 2; $i <= $n; $i++) {
            [$a, $b] = [$b, $a + $b];
        }

        return $b;
    }
}
