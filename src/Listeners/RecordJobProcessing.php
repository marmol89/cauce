<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Listeners;

use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Log;
use Marmol89\Cauce\Contracts\JobRepository;

class RecordJobProcessing
{
    public function __construct(
        protected JobRepository $jobs,
    ) {
    }

    public function handle(JobProcessing $event): void
    {
        try {
            $cauceId = $this->resolveCauceId($event);

            if ($cauceId === null) {
                return;
            }

            $this->jobs->markProcessing($cauceId);
        } catch (\Throwable $e) {
            Log::warning('Cauce: failed to mark processing', [
                'job' => $event->job->resolveName() ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function resolveCauceId(JobProcessing $event): ?string
    {
        $payload = $event->job->payload();
        $uuid = is_array($payload) ? ($payload['uuid'] ?? null) : null;
        $uuid ??= $event->job->getJobId();

        if ($uuid === null || $uuid === '') {
            return null;
        }

        return $this->jobs->findIdByUuid((string) $uuid);
    }
}
