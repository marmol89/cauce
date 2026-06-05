<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Retry;

use Illuminate\Database\ConnectionInterface;
use Marmol89\Cauce\Contracts\RetryStrategy;
use Marmol89\Cauce\Support\AlertManager;

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
                $this->transitionTo(self::STATE_HALF_OPEN);

                return self::STATE_HALF_OPEN;
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
        $row = $this->getRow();

        if ($row !== null && $row->state === self::STATE_HALF_OPEN) {
            $this->transitionTo(self::STATE_CLOSED);
        }

        $this->upsertRow([
            'state' => self::STATE_CLOSED,
            'failures' => 0,
            'opened_at' => null,
            'half_open_at' => null,
            'closed_at' => now(),
        ]);
    }

    public function recordFailure(): void
    {
        $row = $this->getRow();

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

    protected function transitionTo(string $state): void
    {
        $row = $this->getRow();

        $data = [
            'state' => $state,
            'failures' => $row->failures ?? 0,
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

    protected function upsertRow(array $data): void
    {
        $data['threshold'] = $this->threshold;
        $data['cooldown'] = $this->cooldown;
        $data['updated_at'] = now();

        $exists = $this->connection()->table('cauce_circuit_breakers')
            ->where('key', $this->key())
            ->exists();

        if ($exists) {
            $this->connection()->table('cauce_circuit_breakers')
                ->where('key', $this->key())
                ->update($data);
        } else {
            $data['key'] = $this->key();
            $data['created_at'] = now();
            $this->connection()->table('cauce_circuit_breakers')
                ->insert($data);
        }
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
