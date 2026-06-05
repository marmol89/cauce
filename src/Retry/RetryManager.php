<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Retry;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;
use Marmol89\Cauce\Attributes\Retry as RetryAttribute;
use Marmol89\Cauce\Contracts\RetryStrategy;
use ReflectionClass;

class RetryManager
{
    public function __construct(
        protected Container $container,
    ) {
    }

    /**
     * Resolve the retry strategy configured on a job class.
     */
    public function forJob(object|string $job): ?RetryStrategy
    {
        $class = is_object($job) ? $job::class : $job;

        $attribute = $this->resolveAttribute($class);

        if ($attribute === null) {
            return $this->resolveDefault();
        }

        $strategyClass = $attribute->strategy;
        $args = $this->buildArgs($attribute, $strategyClass);

        return $this->container->make($strategyClass, $args);
    }

    /**
     * @param class-string $jobClass
     */
    public function resolveAttribute(string $jobClass): ?RetryAttribute
    {
        $reflection = new ReflectionClass($jobClass);

        $attributes = $reflection->getAttributes(RetryAttribute::class);

        if ($attributes === []) {
            $parent = $reflection->getParentClass();

            if ($parent !== false) {
                $attributes = $parent->getAttributes(RetryAttribute::class);
            }
        }

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildArgs(RetryAttribute $attribute, string $strategyClass): array
    {
        if ($strategyClass === CircuitBreaker::class) {
            return [
                'threshold' => $attribute->threshold,
                'cooldown' => $attribute->cooldown,
                'maxAttempts' => $attribute->max,
                'key' => $attribute->key,
            ];
        }

        return [
            'maxAttempts' => $attribute->max,
            'base' => $attribute->base,
            'cap' => $attribute->cap,
        ];
    }

    protected function resolveDefault(): ?RetryStrategy
    {
        $class = (string) config('cauce.retry.default_strategy');

        if ($class === '') {
            return null;
        }

        $globalMax = config('cauce.retry.global_max_attempts');

        if (is_a($class, CircuitBreaker::class, true)) {
            $args = [
                'threshold' => (int) config('cauce.retry.default_threshold', 5),
                'cooldown' => (int) config('cauce.retry.default_cooldown', 60),
                'maxAttempts' => $globalMax ?? 5,
                'key' => config('cauce.retry.default_breaker_key', 'default'),
            ];
        } else {
            $args = [
                'maxAttempts' => $globalMax ?? 5,
                'base' => (int) config('cauce.retry.default_base', 1),
                'cap' => (int) config('cauce.retry.default_cap', 300),
            ];
        }

        return $this->container->make($class, $args);
    }
}
