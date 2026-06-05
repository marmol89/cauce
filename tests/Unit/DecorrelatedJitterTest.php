<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests\Unit;

use Marmol89\Cauce\Retry\DecorrelatedJitter;
use PHPUnit\Framework\TestCase;

class DecorrelatedJitterTest extends TestCase
{
	public function test_delay_never_exceeds_cap(): void
	{
		$strategy = new DecorrelatedJitter(maxAttempts: 5, base: 1, cap: 100);

		for ($i = 1; $i <= 5; $i++) {
			$delay = $strategy->delay($i);
			$this->assertGreaterThanOrEqual(1, $delay);
			$this->assertLessThanOrEqual(100, $delay);
		}
	}

	public function test_delay_produces_variation(): void
	{
		$strategy = new DecorrelatedJitter(maxAttempts: 5, base: 1, cap: 1000);

		$delays = [];
		for ($i = 0; $i < 50; $i++) {
			$delays[] = $strategy->delay(3);
		}

		$unique = array_unique($delays);

		$this->assertGreaterThan(5, count($unique), 'Jitter should produce a reasonable spread of values.');
	}

	public function test_name(): void
	{
		$this->assertSame('decorrelated-jitter', (new DecorrelatedJitter())->name());
	}
}
