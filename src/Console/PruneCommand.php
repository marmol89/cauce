<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Marmol89\Cauce\Contracts\JobRepository;
use Marmol89\Cauce\Contracts\MetricsRepository;

class PruneCommand extends Command
{
    protected $signature = 'cauce:prune
                            {--completed= : Override the completed retention hours}
                            {--failed= : Override the failed retention days}
                            {--metrics= : Override the metrics retention days}
                            {--force : Skip confirmation}';

    protected $description = 'Prune old Cauce records according to the retention configuration.';

    public function handle(JobRepository $jobs, MetricsRepository $metrics): int
    {
        $completedHours = (int) ($this->option('completed') ?? config('cauce.retention.completed_hours', 24));
        $failedDays = (int) ($this->option('failed') ?? config('cauce.retention.failed_days', 7));
        $metricsDays = (int) ($this->option('metrics') ?? config('cauce.retention.metrics_days', 30));

        $this->components->info("Pruning records older than:");
        $this->components->twoLineDetail(
            "<fg=cyan>Completed:</>  $completedHours hours",
            "<fg=cyan>Failed:</>     $failedDays days / <fg=cyan>Metrics:</>  $metricsDays days",
        );

        if (! $this->option('force') && ! $this->components->confirm('Continue?', true)) {
            $this->components->warn('Aborted.');

            return self::FAILURE;
        }

        $completedBefore = CarbonImmutable::now()->subHours($completedHours);
        $failedBefore = CarbonImmutable::now()->subDays($failedDays);
        $metricsBefore = CarbonImmutable::now()->subDays($metricsDays);

        $completed = $jobs->pruneCompleted($completedBefore);
        $failed = $jobs->pruneFailed($failedBefore);
        $metricsCount = $metrics->prune($metricsBefore);

        $this->newLine();
        $this->components->twoLineDetail(
            "<fg=green>Pruned completed:</>  $completed rows",
            "<fg=green>Pruned failed:</>     $failed rows",
        );
        $this->components->twoLineDetail(
            "<fg=green>Pruned metrics:</>    $metricsCount rows",
            '',
        );

        $this->components->info('Prune complete.');

        return self::SUCCESS;
    }
}
