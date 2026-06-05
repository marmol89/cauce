<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Events;

use Illuminate\Foundation\Events\Dispatchable;

class JobRetried
{
    use Dispatchable;

    public function __construct(
        public readonly string $cauceId,
        public readonly string $jobName,
        public readonly string $connection,
        public readonly string $queue,
    ) {
    }
}
