<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Http\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Marmol89\Cauce\Contracts\JobRepository;
use Marmol89\Cauce\Contracts\MetricsRepository;

class Dashboard extends Component
{
    public int $hours = 24;

    public function mount(): void
    {
        $this->hours = (int) config('cauce.dashboard.refresh_hours', 24);
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
                'by_status' => app(JobRepository::class)->countsByStatus($this->hours),
                'by_connection' => app(JobRepository::class)->countsByConnection($this->hours),
                'by_queue' => app(JobRepository::class)->countsByQueue($this->hours),
                'totals' => app(MetricsRepository::class)->totals('*', '*', $from, CarbonImmutable::now()),
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
