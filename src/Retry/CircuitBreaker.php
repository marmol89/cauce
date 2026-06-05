<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Retry;

use Illuminate\Contracts\Cache\Repository as Cache;
use Marmol89\Cauce\Contracts\RetryStrategy;

class CircuitBreaker implements RetryStrategy
{
    public const STATE_CLOSED = 'closed';
    public const STATE_OPEN = 'open';
    public const STATE_HALF_OPEN = 'half_open';

    public function __construct(
        protected int $threshold = 5,
        protected int $cooldown = 60,
        protected int $maxAttempts = 5,
        protected int $base = 1,
        protected ?string $key = null,
        protected ?Cache $cache = null,
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
        return 'cauce:circuit:' . ($this->key ?? 'default');
    }

    public function state(): string
    {
        $data = $this->getState();

        if ($data === null) {
            return self::STATE_CLOSED;
        }

        if ($data['state'] === self::STATE_OPEN) {
            $openedAt = strtotime((string) $data['opened_at']);
            if ($openedAt !== false && (time() - $openedAt) >= $this->cooldown) {
                $this->transitionTo(self::STATE_HALF_OPEN);

                return self::STATE_HALF_OPEN;
            }
        }

        return (string) $data['state'];
    }

    public function allows(): bool
    {
        return $this->state() !== self::STATE_OPEN;
    }

    public function recordSuccess(): void
    {
        $data = $this->getState();

        if ($data !== null && $data['state'] === self::STATE_HALF_OPEN) {
            $this->transitionTo(self::STATE_CLOSED);
        }

        $this->saveState([
            'state' => self::STATE_CLOSED,
            'failures' => 0,
            'opened_at' => null,
            'half_open_at' => null,
            'closed_at' => now()->toDateTimeString(),
        ]);
    }

    public function recordFailure(): void
    {
        $data = $this->getState() ?? [
            'state' => self::STATE_CLOSED,
            'failures' => 0,
            'opened_at' => null,
            'half_open_at' => null,
            'closed_at' => null,
        ];

        $data['failures'] = (int) $data['failures'] + 1;

        if ($data['failures'] >= $this->threshold) {
            $this->transitionTo(self::STATE_OPEN);
            $data['state'] = self::STATE_OPEN;
            $data['opened_at'] = now()->toDateTimeString();
        }

        $this->saveState($data);
    }

    public function reset(): void
    {
        $this->cache()->forget($this->key());
    }

    protected function transitionTo(string $state): void
    {
        $data = $this->getState() ?? [
            'state' => $state,
            'failures' => 0,
            'opened_at' => null,
            'half_open_at' => null,
            'closed_at' => null,
        ];

        $data['state'] = $state;
        $data['half_open_at'] = $state === self::STATE_HALF_OPEN ? now()->toDateTimeString() : null;
        $data['closed_at'] = $state === self::STATE_CLOSED ? now()->toDateTimeString() : null;

        $this->saveState($data);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getState(): ?array
    {
        $value = $this->cache()->get($this->key());

        return is_array($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function saveState(array $data): void
    {
        $this->cache()->put($this->key(), $data, now()->addDays(7));
    }

    protected function cache(): Cache
    {
        return $this->cache ?? app('cache.store');
    }
}
