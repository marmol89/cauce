<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Listeners;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Support\Facades\Log;
use Marmol89\Cauce\Contracts\JobRepository;
use Marmol89\Cauce\Support\JobPayloadExtractor;

class RecordJobExceptionOccurred
{
    public function __construct(
        protected JobRepository $jobs,
        protected JobPayloadExtractor $extractor,
    ) {
    }

    public function handle(JobExceptionOccurred $event): void
    {
        try {
            $cauceId = $this->resolveCauceId($event);

            if ($cauceId === null) {
                return;
            }

            $data = $this->extractor->fromException($event);

            $this->jobs->recordException($cauceId, $data['exception'] ?? '');
        } catch (\Throwable $e) {
            Log::warning('Cauce: failed to record job exception', [
                'job' => $event->job->resolveName() ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function resolveCauceId(JobExceptionOccurred $event): ?string
    {
        $payload = method_exists($event->job, 'getPayload') ? $event->job->getPayload() : [];

        return $payload['cauce_id'] ?? null;
    }
}
