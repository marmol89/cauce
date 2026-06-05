<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Http\Livewire;

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
        $this->perPage = (int) config('cauce.dashboard.rows_per_page', 25);
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
        $this->jobRepository->retry($id);
        $this->dispatch('cauce:refresh');
    }

    public function delete(string $id): void
    {
        $this->jobRepository->delete($id);
        $this->dispatch('cauce:refresh');
    }
}
