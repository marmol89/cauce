<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Marmol89\Cauce\Contracts\JobRepository;
use Marmol89\Cauce\Contracts\MetricsRepository;
use Marmol89\Cauce\Listeners\RecordJobFailed;
use Marmol89\Cauce\Listeners\RecordJobProcessed;
use Marmol89\Cauce\Listeners\RecordJobProcessing;
use Marmol89\Cauce\Listeners\RecordJobQueued;
use Marmol89\Cauce\Tests\TestCase;

class ListenerRegistrationTest extends TestCase
{
    public function test_listeners_are_registered(): void
    {
        $events = app('events');
        $this->assertNotNull($events);
    }

    public function test_record_queued_listener_creates_row(): void
    {
        $listener = app(RecordJobQueued::class);

        $mockJob = new class {
            public function getQueue(): string
            {
                return 'default';
            }
            public function resolveName(): string
            {
                return 'TestJob';
            }
            public function payload(): array
            {
                return ['displayName' => 'TestJob', 'data' => []];
            }
        };

        $payload = json_encode(['displayName' => 'TestJob', 'data' => []]);
        $event = new JobQueued('database', 'default', 'uuid-1', $mockJob, $payload, null);

        $listener->handle($event);

        $row = app(JobRepository::class)->paginate();
        $this->assertGreaterThanOrEqual(1, $row->total());
    }
}
