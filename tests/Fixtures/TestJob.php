<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class TestJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;

    public function __construct(public string $value = 'noop')
    {
    }

    public function handle(): void
    {
    }
}
