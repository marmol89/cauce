<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Support;

class ShouldTrack
{
    public static function connection(?string $name): bool
    {
        $allowed = (array) config('cauce.monitoring.enabled_connections', ['*']);

        if (in_array('*', $allowed, true)) {
            return true;
        }

        return $name !== null && in_array($name, $allowed, true);
    }

    public static function queue(?string $name): bool
    {
        $allowed = (array) config('cauce.monitoring.enabled_queues', ['*']);

        if (in_array('*', $allowed, true)) {
            return true;
        }

        return $name !== null && in_array($name, $allowed, true);
    }

    public static function sampled(): bool
    {
        $rate = (float) config('cauce.monitoring.sample_rate', 1.0);

        if ($rate >= 1.0) {
            return true;
        }

        if ($rate <= 0.0) {
            return false;
        }

        return mt_rand() / mt_getrandmax() <= $rate;
    }
}
