<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Events;

use Illuminate\Foundation\Events\Dispatchable;

class CircuitBreakerOpened
{
    use Dispatchable;

    public function __construct(
        public readonly string $key,
        public readonly int $threshold,
    ) {
    }
}
