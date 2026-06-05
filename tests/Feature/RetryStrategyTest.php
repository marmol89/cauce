<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests\Feature;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Marmol89\Cauce\Attributes\Retry;
use Marmol89\Cauce\Retry\ExponentialBackoff;
use Marmol89\Cauce\Retry\LinearBackoff;
use Marmol89\Cauce\Retry\RetryManager;
use Marmol89\Cauce\Tests\TestCase;

#[Retry(strategy: LinearBackoff::class, max: 5, base: 3, cap: 60)]
class TaggedTestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
}

class RetryStrategyTest extends TestCase
{
    public function test_resolve_strategy_from_attribute(): void
    {
        $manager = app(RetryManager::class);
        $strategy = $manager->forJob(TaggedTestJob::class);

        $this->assertInstanceOf(LinearBackoff::class, $strategy);
        $this->assertSame(5, $strategy->maxAttempts());
    }

    public function test_returns_null_when_no_attribute(): void
    {
        $manager = app(RetryManager::class);
        $strategy = $manager->forJob(\stdClass::class);

        $this->assertNull($strategy);
    }

    public function test_default_strategy_from_config(): void
    {
        config()->set('cauce.retry.default_strategy', ExponentialBackoff::class);
        config()->set('cauce.retry.global_max_attempts', 4);

        $manager = app(RetryManager::class);
        $strategy = $manager->forJob(\stdClass::class);

        $this->assertInstanceOf(ExponentialBackoff::class, $strategy);
        $this->assertSame(4, $strategy->maxAttempts());
    }
}
