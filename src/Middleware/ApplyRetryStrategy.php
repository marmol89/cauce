<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Middleware;

use Closure;
use Marmol89\Cauce\Contracts\RetryStrategy;
use Marmol89\Cauce\Retry\CircuitBreaker;

class ApplyRetryStrategy
{
    public function __construct(
        protected RetryStrategy $strategy,
    ) {
    }

    public function handle(object $job, Closure $next): mixed
    {
        if ($this->strategy instanceof CircuitBreaker && ! $this->strategy->allows()) {
            if (method_exists($job, 'release')) {
                $job->release($this->strategy->delay(1));
            }

            return null;
        }

        $result = $next($job);

        if ($this->strategy instanceof CircuitBreaker) {
            $this->strategy->recordSuccess();
        }

        return $result;
    }

    public function failed(object $job, \Throwable $e): void
    {
        if ($this->strategy instanceof CircuitBreaker) {
            $this->strategy->recordFailure();
        }
    }
}
