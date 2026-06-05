<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Http\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Marmol89\Cauce\Contracts\JobRepository;
use Marmol89\Cauce\Contracts\MetricsRepository;

class Dashboard extends Component
{
    public int $hours = 24;

    protected JobRepository $jobRepository;
    protected MetricsRepository $metricsRepository;

    public function boot(JobRepository $jobs, MetricsRepository $metrics): void
    {
        $this->jobRepository = $jobs;
        $this->metricsRepository = $metrics;
    }

    public function mount(): void
    {
        Gate::authorize('viewCauce');

        $this->hours = (int) config('cauce.dashboard.refresh_hours', 24);
        $this->hours = max(1, min(168, $this->hours));
    }

    public function updatedHours(): void
    {
        $this->hours = max(1, min(168, $this->hours));
    }

    public function render()
    {
        return view('cauce::livewire.dashboard');
    }

    #[Computed]
    public function stats(): array
    {
        $cacheKey = 'cauce:dashboard:' . $this->hours;

        return Cache::remember($cacheKey, 30, function () {
            $from = CarbonImmutable::now()->subHours($this->hours);

            return [
                'by_status' => $this->jobRepository->countsByStatus($this->hours),
                'by_connection' => $this->jobRepository->countsByConnection($this->hours),
                'by_queue' => $this->jobRepository->countsByQueue($this->hours),
                'totals' => $this->metricsRepository->totals('*', '*', $from, CarbonImmutable::now()),
            ];
        });
    }

    #[On('cauce:refresh')]
    public function refresh(): void
    {
        Cache::forget('cauce:dashboard:' . $this->hours);
        unset($this->stats);
    }
}
