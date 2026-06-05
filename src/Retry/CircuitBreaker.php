<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Retry;

use Illuminate\Database\ConnectionInterface;
use Marmol89\Cauce\Contracts\RetryStrategy;
use Marmol89\Cauce\Events\CircuitBreakerClosed;
use Marmol89\Cauce\Events\CircuitBreakerHalfOpened;
use Marmol89\Cauce\Events\CircuitBreakerOpened;

/**
 * Thread-safe circuit breaker backed by the database.
 *
 * State transitions use pessimistic row locking (SELECT ... FOR UPDATE inside
 * a transaction) to prevent race conditions when multiple workers process jobs
 * sharing the same circuit breaker key. This serializes concurrent transitions,
 * which is correct but may become a bottleneck if thousands of workers compete
 * for the same key simultaneously. Tune the threshold and cooldown so the
 * breaker trips early enough to reduce contention.
 */
class CircuitBreaker implements RetryStrategy
{
    public const STATE_CLOSED = 'closed';
    public const STATE_OPEN = 'open';
    public const STATE_HALF_OPEN = 'half_open';

    protected ?ConnectionInterface $connection = null;

    public function __construct(
        protected int $threshold = 5,
        protected int $cooldown = 60,
        protected int $maxAttempts = 5,
        protected int $base = 1,
        protected ?string $key = null,
    ) {
    }

    public function delay(int $attempt): int
    {
        return $this->base;
    }

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function name(): string
    {
        return 'circuit-breaker';
    }

    public function key(): string
    {
        return $this->key ?? 'default';
    }

    public function state(): string
    {
        $row = $this->getRow();

        if ($row === null) {
            return self::STATE_CLOSED;
        }

        if ($row->state === self::STATE_OPEN) {
            $openedAt = strtotime((string) $row->opened_at);
            if ($openedAt !== false && (time() - $openedAt) >= $this->cooldown) {
                return $this->connection()->transaction(function () {
                    $fresh = $this->getRowLocked();
                    if ($fresh === null || $fresh->state !== self::STATE_OPEN) {
                        return $fresh !== null ? (string) $fresh->state : self::STATE_CLOSED;
                    }

                    $recheck = strtotime((string) $fresh->opened_at);
                    if ($recheck === false || (time() - $recheck) < $this->cooldown) {
                        return self::STATE_OPEN;
                    }

                    CircuitBreakerHalfOpened::dispatch($this->key());
                    $this->transitionTo(self::STATE_HALF_OPEN);

                    return self::STATE_HALF_OPEN;
                });
            }
        }

        return (string) $row->state;
    }

    public function allows(): bool
    {
        return $this->state() !== self::STATE_OPEN;
    }

    public function recordSuccess(): void
    {
        $this->connection()->transaction(function () {
            $row = $this->getRowLocked();

            if ($row !== null && $row->state === self::STATE_HALF_OPEN) {
                CircuitBreakerClosed::dispatch($this->key());

                if (app()->bound(AlertManager::class)) {
                    app(AlertManager::class)->notifyCircuitBreakerClosed($this->key());
                }

                $this->transitionToLocked(self::STATE_CLOSED, $row);
                return;
            }

            $this->upsertRow([
                'state' => self::STATE_CLOSED,
                'failures' => 0,
                'opened_at' => null,
                'half_open_at' => null,
                'closed_at' => now(),
            ]);
        });
    }

    public function recordFailure(): void
    {
        $shouldDispatch = false;

        $this->connection()->transaction(function () use (&$shouldDispatch) {
            $row = $this->getRowLocked();

            $failures = $row !== null ? (int) $row->failures + 1 : 1;
            $state = $row !== null ? (string) $row->state : self::STATE_CLOSED;

            $data = [
                'state' => $state,
                'failures' => $failures,
                'opened_at' => $row->opened_at ?? null,
                'half_open_at' => $row->half_open_at ?? null,
                'closed_at' => $row->closed_at ?? null,
            ];

            if ($failures >= $this->threshold) {
                $data['state'] = self::STATE_OPEN;
                $data['opened_at'] = now();
            }

            $this->upsertRow($data);

            if ($data['state'] === self::STATE_OPEN && $data['failures'] === $this->threshold) {
                $shouldDispatch = true;
            }
        });

        if ($shouldDispatch) {
            CircuitBreakerOpened::dispatch($this->key(), $this->threshold);

            if (app()->bound(AlertManager::class)) {
                app(AlertManager::class)->notifyCircuitBreakerOpen($this->key());
            }
        }
    }

    public function reset(): void
    {
        $this->connection()->table('cauce_circuit_breakers')
            ->where('key', $this->key())
            ->delete();
    }

    public static function openKeys(): array
    {
        try {
            $name = config('cauce.storage.database.connection');

            /** @var \Illuminate\Database\ConnectionResolverInterface $resolver */
            $resolver = app('db');
            $connection = $resolver->connection($name);

            return $connection->table('cauce_circuit_breakers')
                ->where('state', self::STATE_OPEN)
                ->pluck('key')
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    protected function transitionTo(string $state): void
    {
        $row = $this->getRow();

        $data = [
            'state' => $state,
            'failures' => $row !== null ? (int) $row->failures : 0,
            'opened_at' => $row->opened_at ?? null,
            'half_open_at' => $row->half_open_at ?? null,
            'closed_at' => $row->closed_at ?? null,
        ];

        if ($state === self::STATE_HALF_OPEN) {
            $data['half_open_at'] = now();
        }

        if ($state === self::STATE_CLOSED) {
            $data['closed_at'] = now();
        }

        $this->upsertRow($data);
    }

    protected function transitionToLocked(string $state, object $row): void
    {
        $data = [
            'state' => $state,
            'failures' => (int) ($row->failures ?? 0),
            'opened_at' => $row->opened_at ?? null,
            'half_open_at' => $row->half_open_at ?? null,
            'closed_at' => $row->closed_at ?? null,
        ];

        if ($state === self::STATE_HALF_OPEN) {
            $data['half_open_at'] = now();
        }

        if ($state === self::STATE_CLOSED) {
            $data['closed_at'] = now();
        }

        $this->upsertRow($data);
    }

    protected function getRow(): ?object
    {
        return $this->connection()->table('cauce_circuit_breakers')
            ->where('key', $this->key())
            ->first();
    }

    protected function getRowLocked(): ?object
    {
        return $this->connection()->table('cauce_circuit_breakers')
            ->where('key', $this->key())
            ->lockForUpdate()
            ->first();
    }

    protected function upsertRow(array $data): void
    {
        $values = [
            'key' => $this->key(),
            'state' => $data['state'],
            'failures' => $data['failures'],
            'threshold' => $this->threshold,
            'cooldown' => $this->cooldown,
            'opened_at' => $data['opened_at'] ?? null,
            'half_open_at' => $data['half_open_at'] ?? null,
            'closed_at' => $data['closed_at'] ?? null,
            'updated_at' => now(),
            'created_at' => now(),
        ];

        $this->connection()->table('cauce_circuit_breakers')->upsert(
            $values,
            ['key'],
            [
                'state', 'failures', 'threshold', 'cooldown',
                'opened_at', 'half_open_at', 'closed_at', 'updated_at',
            ],
        );
    }

    protected function connection(): ConnectionInterface
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        $name = config('cauce.storage.database.connection');

        /** @var \Illuminate\Database\ConnectionResolverInterface $resolver */
        $resolver = app('db');

        return $this->connection = $resolver->connection($name);
    }
}
