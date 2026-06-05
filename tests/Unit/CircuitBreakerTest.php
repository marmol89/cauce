<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests\Unit;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Marmol89\Cauce\Retry\CircuitBreaker;
use PHPUnit\Framework\TestCase;

class CircuitBreakerTest extends TestCase
{
    protected function makeBreaker(int $threshold = 3, int $cooldown = 60): CircuitBreaker
    {
        return new CircuitBreaker(
            threshold: $threshold,
            cooldown: $cooldown,
            key: 'test-' . uniqid(),
            cache: new CacheRepository(new ArrayStore()),
        );
    }

    public function test_starts_closed(): void
    {
        $breaker = $this->makeBreaker();

        $this->assertTrue($breaker->allows());
        $this->assertSame(CircuitBreaker::STATE_CLOSED, $breaker->state());
    }

    public function test_opens_after_threshold_failures(): void
    {
        $breaker = $this->makeBreaker(threshold: 3);

        $breaker->recordFailure();
        $breaker->recordFailure();
        $this->assertTrue($breaker->allows());

        $breaker->recordFailure();
        $this->assertFalse($breaker->allows());
        $this->assertSame(CircuitBreaker::STATE_OPEN, $breaker->state());
    }

    public function test_success_resets_failures(): void
    {
        $breaker = $this->makeBreaker(threshold: 3);

        $breaker->recordFailure();
        $breaker->recordFailure();
        $breaker->recordSuccess();

        $this->assertTrue($breaker->allows());
    }

    public function test_reset_clears_state(): void
    {
        $breaker = $this->makeBreaker(threshold: 2);

        $breaker->recordFailure();
        $breaker->recordFailure();
        $this->assertFalse($breaker->allows());

        $breaker->reset();
        $this->assertTrue($breaker->allows());
    }
}
