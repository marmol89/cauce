<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Events;

use Illuminate\Foundation\Events\Dispatchable;

class AlertTriggered
{
    use Dispatchable;

    public function __construct(
        public readonly string $type,
        public readonly string $message,
        public readonly array $context = [],
    ) {
    }
}
