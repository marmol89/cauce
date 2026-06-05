<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests\Unit;

use Marmol89\Cauce\Retry\FibonacciBackoff;
use PHPUnit\Framework\TestCase;

class FibonacciBackoffTest extends TestCase
{
	public function test_fibonacci_known_values(): void
	{
		$strategy = new FibonacciBackoff(maxAttempts: 10, base: 1, cap: 1000);

		// 1, 1, 2, 3, 5, 8, 13, 21, 34, 55
		$this->assertSame(1, $strategy->delay(1));
		$this->assertSame(1, $strategy->delay(2));
		$this->assertSame(2, $strategy->delay(3));
		$this->assertSame(3, $strategy->delay(4));
		$this->assertSame(5, $strategy->delay(5));
		$this->assertSame(8, $strategy->delay(6));
		$this->assertSame(13, $strategy->delay(7));
	}

	public function test_fibonacci_is_capped(): void
	{
		$strategy = new FibonacciBackoff(maxAttempts: 20, base: 1, cap: 30);

		$this->assertSame(30, $strategy->delay(20));
	}

	public function test_name(): void
	{
		$this->assertSame('fibonacci', (new FibonacciBackoff())->name());
	}

	public function test_max_attempts(): void
	{
		$strategy = new FibonacciBackoff(maxAttempts: 7);

		$this->assertSame(7, $strategy->maxAttempts());
	}

	public function test_base_multiplier(): void
	{
		$strategy = new FibonacciBackoff(maxAttempts: 5, base: 3, cap: 1000);

		$this->assertSame(3, $strategy->delay(1));
		$this->assertSame(6, $strategy->delay(3)); // fib(3)=2 * base=3 = 6
	}
}
