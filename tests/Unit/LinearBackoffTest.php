<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests\Unit;

use Marmol89\Cauce\Retry\LinearBackoff;
use PHPUnit\Framework\TestCase;

class LinearBackoffTest extends TestCase
{
    public function test_delay_grows_linearly(): void
    {
        $strategy = new LinearBackoff(maxAttempts: 5, base: 5, cap: 300);

        $this->assertSame(5, $strategy->delay(1));
        $this->assertSame(10, $strategy->delay(2));
        $this->assertSame(15, $strategy->delay(3));
        $this->assertSame(20, $strategy->delay(4));
    }

    public function test_delay_is_capped(): void
    {
        $strategy = new LinearBackoff(maxAttempts: 100, base: 10, cap: 25);

        $this->assertSame(25, $strategy->delay(5));
        $this->assertSame(25, $strategy->delay(50));
    }

    public function test_max_attempts(): void
    {
        $strategy = new LinearBackoff(maxAttempts: 7);
        $this->assertSame(7, $strategy->maxAttempts());
    }

    public function test_name(): void
    {
        $this->assertSame('linear', (new LinearBackoff())->name());
    }
}
