<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests\Feature;

use Marmol89\Cauce\Contracts\RetryStrategy;
use Marmol89\Cauce\Middleware\ApplyRetryStrategy;
use Marmol89\Cauce\Retry\CircuitBreaker;
use Marmol89\Cauce\Tests\TestCase;

class ApplyRetryStrategyTest extends TestCase
{
	public function test_can_be_instantiated_with_a_strategy(): void
	{
		$strategy = $this->createStub(RetryStrategy::class);
		$middleware = new ApplyRetryStrategy($strategy);

		$this->assertInstanceOf(ApplyRetryStrategy::class, $middleware);
	}

	public function test_when_circuit_breaker_is_open_job_is_released_with_delay(): void
	{
		$strategy = $this->createMock(CircuitBreaker::class);
		$strategy->method('allows')->willReturn(false);
		$strategy->expects($this->once())
			->method('delay')
			->with(1)
			->willReturn(30);

		$releasedWith = null;
		$job = new class($releasedWith) {
			public ?int $releasedWith;

			public function __construct(?int &$releasedWith)
			{
				$this->releasedWith = &$releasedWith;
			}

			public function release(int $delay = 0): void
			{
				$this->releasedWith = $delay;
			}
		};

		$next = function (): void {
			$this->fail('Next should not be called when circuit breaker is open.');
		};

		$middleware = new ApplyRetryStrategy($strategy);
		$result = $middleware->handle($job, $next);

		$this->assertNull($result);
		$this->assertSame(30, $releasedWith);
	}

	public function test_when_circuit_breaker_allows_calls_record_success_on_success(): void
	{
		$strategy = $this->createMock(CircuitBreaker::class);
		$strategy->method('allows')->willReturn(true);
		$strategy->expects($this->once())
			->method('recordSuccess');

		$job = new \stdClass();

		$nextCalled = false;
		$next = function ($job) use (&$nextCalled) {
			$nextCalled = true;

			return 'success';
		};

		$middleware = new ApplyRetryStrategy($strategy);
		$result = $middleware->handle($job, $next);

		$this->assertTrue($nextCalled);
		$this->assertSame('success', $result);
	}

	public function test_calls_record_failure_on_exception(): void
	{
		$strategy = $this->createMock(CircuitBreaker::class);
		$strategy->expects($this->once())
			->method('recordFailure');

		$job = new \stdClass();
		$exception = new \RuntimeException('test failure');

		$middleware = new ApplyRetryStrategy($strategy);
		$middleware->failed($job, $exception);
	}

	public function test_does_not_record_success_or_failure_for_non_circuit_breaker_strategies(): void
	{
		$strategy = $this->createStub(RetryStrategy::class);

		$job = new \stdClass();

		$nextCalled = false;
		$next = function ($job) use (&$nextCalled) {
			$nextCalled = true;

			return 'done';
		};

		$middleware = new ApplyRetryStrategy($strategy);
		$result = $middleware->handle($job, $next);

		$this->assertTrue($nextCalled);
		$this->assertSame('done', $result);

		$middleware->failed($job, new \RuntimeException('test'));
		$this->assertTrue(true);
	}
}
