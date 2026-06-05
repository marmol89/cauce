<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests\Unit;

use Marmol89\Cauce\Retry\CircuitBreaker;
use Marmol89\Cauce\Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
	protected function makeBreaker(int $threshold = 3, int $cooldown = 60): CircuitBreaker
	{
		return new CircuitBreaker(
			threshold: $threshold,
			cooldown: $cooldown,
			key: 'test-' . uniqid(),
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

	public function test_record_failure_without_prior_state(): void
	{
		$breaker = $this->makeBreaker(threshold: 1);

		$breaker->recordFailure();
		$this->assertFalse($breaker->allows());
	}

	public function test_half_open_transitions(): void
	{
		$breaker = $this->makeBreaker(threshold: 1, cooldown: 0);

		$breaker->recordFailure();

		// With cooldown 0, cooldown has already passed, so state transitions
		// to half_open immediately when checked.
		$this->assertSame(CircuitBreaker::STATE_HALF_OPEN, $breaker->state());
	}
}
