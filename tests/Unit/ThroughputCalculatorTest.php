<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests\Unit;

use Marmol89\Cauce\Support\ThroughputCalculator;
use PHPUnit\Framework\TestCase;

class ThroughputCalculatorTest extends TestCase
{
    public function test_bucket_aligns_to_window(): void
    {
        $date = new \DateTimeImmutable('2026-01-15 10:23:45');
        $bucket = ThroughputCalculator::bucket($date, 60);

        $this->assertSame(0, (int) $bucket->format('S'));
        $this->assertSame(23, (int) $bucket->format('i'));
    }

    public function test_average_throughput(): void
    {
        $this->assertSame(0.0, ThroughputCalculator::average(0, 60));
        $this->assertSame(1.0, ThroughputCalculator::average(1, 60));
        $this->assertSame(2.0, ThroughputCalculator::average(2, 60));
        $this->assertSame(60.0, ThroughputCalculator::average(60, 60));
    }

    public function test_average_with_zero_window(): void
    {
        $this->assertSame(0.0, ThroughputCalculator::average(5, 0));
    }
}
