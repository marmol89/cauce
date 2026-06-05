<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Http\Livewire;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Marmol89\Cauce\Contracts\JobRepository;

class FailedJobsTable extends Component
{
    use WithPagination;

    public int $perPage;

    protected JobRepository $jobRepository;

    public function boot(JobRepository $jobs): void
    {
        $this->jobRepository = $jobs;
    }

    public function mount(): void
    {
        Gate::authorize('viewCauce');

        $this->perPage = (int) config('cauce.dashboard.rows_per_page', 25);
        $this->perPage = max(5, min(100, $this->perPage));
    }

    public function updatedPerPage(): void
    {
        $this->perPage = max(5, min(100, $this->perPage));
    }

    public function render()
    {
        return view('cauce::livewire.failed-jobs-table');
    }

    #[Computed]
    public function jobs()
    {
        return $this->jobRepository->failed($this->perPage);
    }

    public function retry(string $id): void
    {
        Gate::authorize('mutateCauce');

        $this->jobRepository->retry($id);
        $this->dispatch('cauce:refresh');
    }

    public function delete(string $id): void
    {
        Gate::authorize('mutateCauce');

        $this->jobRepository->delete($id);
        $this->dispatch('cauce:refresh');
    }
}
