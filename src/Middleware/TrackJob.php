<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Middleware;

use Closure;
use Marmol89\Cauce\Retry\RetryManager;

class TrackJob
{
    public function __construct(
        protected RetryManager $manager,
    ) {
    }

    public function handle(object $job, Closure $next): mixed
    {
        if (method_exists($job, 'getPayload')) {
            $payload = $job->getPayload();
            $strategy = $this->manager->forJob($job);

            if ($strategy !== null) {
                $payload['cauce_retry_strategy'] = $strategy->name();
                $payload['cauce_retry_max'] = $strategy->maxAttempts();
            }
        }

        return $next($job);
    }
}
