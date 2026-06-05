<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Listeners;

use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Log;
use Marmol89\Cauce\Contracts\JobRepository;
use Marmol89\Cauce\Contracts\MetricsRepository;
use Marmol89\Cauce\Support\JobPayloadExtractor;
use Marmol89\Cauce\Support\ShouldTrack;

class RecordJobQueued
{
    public function __construct(
        protected JobRepository $jobs,
        protected MetricsRepository $metrics,
        protected JobPayloadExtractor $extractor,
    ) {
    }

    public function handle(JobQueued $event): void
    {
        try {
            if (! $this->shouldTrack($event->connectionName, $event->queue ?: 'default')) {
                return;
            }

            if (! ShouldTrack::sampled()) {
                return;
            }

            $data = $this->extractor->fromQueued($event);
            $this->jobs->recordQueued($data);
        } catch (\Throwable $e) {
            Log::warning('Cauce: failed to record queued job', [
                'connection' => $event->connectionName ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function shouldTrack(?string $connection, ?string $queue): bool
    {
        return ShouldTrack::connection($connection) && ShouldTrack::queue($queue);
    }
}
