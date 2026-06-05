<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests\Unit;

use Marmol89\Cauce\Retry\DecorrelatedJitter;
use Marmol89\Cauce\Retry\FibonacciBackoff;
use Marmol89\Cauce\Retry\LinearBackoff;
use Marmol89\Cauce\Retry\ExponentialBackoff;
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
}
