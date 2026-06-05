<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use Marmol89\Cauce\Contracts\JobRepository;
use Marmol89\Cauce\Tests\Fixtures\TestJob;
use Marmol89\Cauce\Tests\TestCase;

class JobTrackingTest extends TestCase
{
    public function test_record_queued_returns_id(): void
    {
        $repo = app(JobRepository::class);

        $id = $repo->recordQueued([
            'uuid' => 'test-uuid',
            'connection' => 'database',
            'queue' => 'default',
            'name' => 'App\\Jobs\\TestJob',
            'status' => 'queued',
            'queued_at' => now(),
        ]);

        $this->assertNotEmpty($id);
        $this->assertNotNull($repo->find($id));
    }

    public function test_mark_processed_updates_runtime(): void
    {
        $repo = app(JobRepository::class);

        $id = $repo->recordQueued([
            'uuid' => 'abc',
            'connection' => 'database',
            'queue' => 'default',
            'name' => 'App\\Jobs\\TestJob',
            'status' => 'queued',
            'queued_at' => now(),
        ]);

        $repo->markProcessing($id);
        usleep(1000);
        $repo->markProcessed($id, 50);

        $row = $repo->find($id);
        $this->assertSame('completed', $row->status);
        $this->assertSame(50, (int) $row->runtime_ms);
    }

    public function test_mark_failed_records_exception(): void
    {
        $repo = app(JobRepository::class);

        $id = $repo->recordQueued([
            'uuid' => 'fail',
            'connection' => 'database',
            'queue' => 'default',
            'name' => 'App\\Jobs\\TestJob',
            'status' => 'queued',
            'queued_at' => now(),
        ]);

        $repo->markFailed($id, 'RuntimeException: boom');

        $row = $repo->find($id);
        $this->assertSame('failed', $row->status);
        $this->assertStringContainsString('RuntimeException', $row->exception);
    }

    public function test_counts_by_status(): void
    {
        $repo = app(JobRepository::class);

        for ($i = 0; $i < 3; $i++) {
            $id = $repo->recordQueued([
                'uuid' => "q-$i",
                'connection' => 'database',
                'queue' => 'default',
                'name' => 'App\\Jobs\\TestJob',
                'status' => 'queued',
                'queued_at' => now(),
            ]);
            $repo->markProcessed($id, 10);
        }

        for ($i = 0; $i < 2; $i++) {
            $id = $repo->recordQueued([
                'uuid' => "f-$i",
                'connection' => 'database',
                'queue' => 'default',
                'name' => 'App\\Jobs\\TestJob',
                'status' => 'queued',
                'queued_at' => now(),
            ]);
            $repo->markFailed($id, 'oops');
        }

        $counts = $repo->countsByStatus(1);
        $this->assertSame(3, (int) $counts['completed']);
        $this->assertSame(2, (int) $counts['failed']);
    }

    public function test_pagination(): void
    {
        $repo = app(JobRepository::class);

        for ($i = 0; $i < 30; $i++) {
            $repo->recordQueued([
                'uuid' => "p-$i",
                'connection' => 'database',
                'queue' => 'default',
                'name' => 'App\\Jobs\\TestJob',
                'status' => 'queued',
                'queued_at' => now(),
            ]);
        }

        $page = $repo->paginate([], 10);
        $this->assertSame(30, $page->total());
        $this->assertCount(10, $page->items());
    }

    public function test_retry_resets_record(): void
    {
        Queue::fake();

        $repo = app(JobRepository::class);

        $id = $repo->recordQueued([
            'uuid' => 'retry-1',
            'connection' => config('queue.default'),
            'queue' => 'default',
            'name' => TestJob::class,
            'status' => 'queued',
            'queued_at' => now(),
            'payload' => [
                'uuid' => 'retry-1',
                'displayName' => TestJob::class,
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'data' => [
                    'commandName' => TestJob::class,
                    'command' => serialize(new TestJob('boom')),
                ],
            ],
        ]);
        $repo->markFailed($id, 'boom');

        $this->assertTrue($repo->retry($id));

        $row = $repo->find($id);
        $this->assertSame('queued', $row->status);
        $this->assertNull($row->exception);
    }

    public function test_prune_completed(): void
    {
        $repo = app(JobRepository::class);

        $id = $repo->recordQueued([
            'uuid' => 'old',
            'connection' => 'database',
            'queue' => 'default',
            'name' => 'App\\Jobs\\TestJob',
            'status' => 'queued',
            'queued_at' => now()->subDays(2),
        ]);
        $repo->markProcessing($id);
        $repo->markProcessed($id, 5);

        // Force the row's finished_at into the past so the prune query catches it.
        $conn = \Illuminate\Support\Facades\DB::connection();
        $conn->table('cauce_jobs')
            ->where('id', $id)
            ->update(['finished_at' => now()->subDays(2)->format('Y-m-d H:i:s')]);

        $deleted = $repo->pruneCompleted(\Carbon\CarbonImmutable::now()->subDay());
        $this->assertGreaterThanOrEqual(1, $deleted);
        $this->assertNull($repo->find($id));
    }
}
