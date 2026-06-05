<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Middleware;

use Closure;

class TrackJob
{

    public function handle(object $job, Closure $next): mixed
    {
        return $next($job);
    }
}
