<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Support;

class ThroughputCalculator
{
    public static function bucket(\DateTimeInterface $date, int $window = 60): \DateTimeImmutable
    {
        $timestamp = $date->getTimestamp();
        $bucket = intdiv($timestamp, $window) * $window;

        return (new \DateTimeImmutable())->setTimestamp($bucket);
    }

    public static function average(int $count, int $windowSeconds): float
    {
        if ($windowSeconds <= 0 || $count <= 0) {
            return 0.0;
        }

        return round($count * 60 / $windowSeconds, 2);
    }
}
