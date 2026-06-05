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
        $fib = $this->fibonacci(max(1, $attempt));
        $result = $this->base * $fib;

        if (! is_int($result) || $result < 0) {
            return $this->cap;
        }

        return (int) min($this->cap, $result);
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
        $capDivBase = (int) ($this->cap / max(1, $this->base));

        for ($i = 2; $i <= $n; $i++) {
            $next = $a + $b;
            if ($next > $capDivBase || $next < 0) {
                return $next;
            }
            [$a, $b] = [$b, $next];
        }

        return $b;
    }
}
