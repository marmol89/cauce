<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests\Unit;

use Marmol89\Cauce\Retry\ExponentialBackoff;
use PHPUnit\Framework\TestCase;

class ExponentialBackoffTest extends TestCase
{
    public function test_delay_doubles_each_attempt(): void
    {
        $strategy = new ExponentialBackoff(maxAttempts: 5, base: 2, cap: 1000);

        $this->assertSame(1, $strategy->delay(1));
        $this->assertSame(2, $strategy->delay(2));
        $this->assertSame(4, $strategy->delay(3));
        $this->assertSame(8, $strategy->delay(4));
        $this->assertSame(16, $strategy->delay(5));
    }

    public function test_delay_is_capped(): void
    {
        $strategy = new ExponentialBackoff(maxAttempts: 20, base: 2, cap: 50);

        $this->assertSame(50, $strategy->delay(10));
        $this->assertSame(50, $strategy->delay(20));
    }

    public function test_custom_base(): void
    {
        $strategy = new ExponentialBackoff(maxAttempts: 5, base: 3, cap: 1000);

        $this->assertSame(1, $strategy->delay(1));
        $this->assertSame(3, $strategy->delay(2));
        $this->assertSame(9, $strategy->delay(3));
    }

    public function test_name(): void
    {
        $this->assertSame('exponential', (new ExponentialBackoff())->name());
    }
}
