<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests\Feature;

use Carbon\CarbonImmutable;
use Marmol89\Cauce\Contracts\JobRepository;
use Marmol89\Cauce\Tests\TestCase;

class PruneStuckJobsTest extends TestCase
{
    public function test_prune_completed_removes_jobs_stuck_without_finished_at(): void
    {
        $jobs = app(JobRepository::class);

        $stuckId = $jobs->recordQueued([
            'uuid' => 'stuck-uuid-1',
            'connection' => 'test',
            'queue' => 'default',
            'name' => 'StuckTestJob',
            'status' => 'queued',
            'created_at' => now()->subHours(48),
        ]);

        $recentId = $jobs->recordQueued([
            'uuid' => 'recent-uuid-1',
            'connection' => 'test',
            'queue' => 'default',
            'name' => 'RecentTestJob',
            'status' => 'queued',
            'created_at' => now()->subHours(1),
        ]);

        $cutoff = CarbonImmutable::now()->subHours(25);
        $deleted = $jobs->pruneCompleted($cutoff);

        $this->assertSame(1, $deleted);
        $this->assertNull($jobs->find($stuckId));
        $this->assertNotNull($jobs->find($recentId));
    }

    public function test_prune_completed_removes_retrying_jobs_stuck(): void
    {
        $jobs = app(JobRepository::class);

        $stuckId = $jobs->recordQueued([
            'uuid' => 'retrying-uuid-1',
            'connection' => 'test',
            'queue' => 'default',
            'name' => 'RetryingTestJob',
            'status' => 'retrying',
            'created_at' => now()->subHours(72),
        ]);

        $cutoff = CarbonImmutable::now()->subHours(25);
        $deleted = $jobs->pruneCompleted($cutoff);

        $this->assertSame(1, $deleted);
        $this->assertNull($jobs->find($stuckId));
    }

    public function test_prune_completed_removes_completed_with_finished_at(): void
    {
        $jobs = app(JobRepository::class);

        $oldDate = now()->subHours(48);

        $id = $jobs->recordQueued([
            'uuid' => 'complete-uuid-1',
            'connection' => 'test',
            'queue' => 'default',
            'name' => 'CompleteTestJob',
            'status' => 'completed',
            'created_at' => $oldDate,
        ]);

        $jobs->markProcessing($id);
        $jobs->markProcessed($id, 100);

        // Override finished_at to be old, since markProcessed always uses now()
        \Illuminate\Support\Facades\DB::table('cauce_jobs')
            ->where('id', $id)
            ->update(['finished_at' => $oldDate->addHour()]);

        $cutoff = CarbonImmutable::now()->subHours(25);
        $deleted = $jobs->pruneCompleted($cutoff);

        $this->assertSame(1, $deleted);
        $this->assertNull($jobs->find($id));
    }

    public function test_prune_completed_does_not_remove_recent_jobs(): void
    {
        $jobs = app(JobRepository::class);

        $id = $jobs->recordQueued([
            'uuid' => 'recent-complete-uuid-1',
            'connection' => 'test',
            'queue' => 'default',
            'name' => 'RecentCompleteTestJob',
            'status' => 'queued',
            'created_at' => now()->subHours(2),
        ]);

        $cutoff = CarbonImmutable::now()->subHours(24);
        $deleted = $jobs->pruneCompleted($cutoff);

        $this->assertSame(0, $deleted);
        $this->assertNotNull($jobs->find($id));
    }
}
