<div>
    <div class="cauce-card overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 uppercase text-xs">
                    <th class="px-3 py-2">Job</th>
                    <th class="px-3 py-2">Connection</th>
                    <th class="px-3 py-2">Queue</th>
                    <th class="px-3 py-2">Attempts</th>
                    <th class="px-3 py-2">Failed at</th>
                    <th class="px-3 py-2">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($this->jobs as $job)
                    <tr wire:key="failed-{{ $job->id }}">
                        <td class="px-3 py-2 font-mono text-xs text-slate-700">
                            <a href="{{ route('cauce.jobs.show', $job->id) }}" class="hover:text-indigo-600">
                                {{ \Illuminate\Support\Str::limit($job->name, 40) }}
                            </a>
                        </td>
                        <td class="px-3 py-2 text-slate-600">{{ $job->connection }}</td>
                        <td class="px-3 py-2 text-slate-600">{{ $job->queue }}</td>
                        <td class="px-3 py-2 text-slate-600">{{ $job->attempts }}</td>
                        <td class="px-3 py-2 text-slate-500 text-xs">{{ $job->failed_at?->diffForHumans() ?? '—' }}</td>
                        <td class="px-3 py-2 space-x-2">
                            <button wire:click="retry('{{ addslashes($job->id) }}')" wire:confirm="Re-queue this job?"
                                class="inline-flex items-center px-2.5 py-1 rounded text-xs font-medium bg-indigo-600 text-white hover:bg-indigo-700" wire:loading.attr="disabled">
                                Retry
                            </button>
                            <button wire:click="delete('{{ addslashes($job->id) }}')" wire:confirm="Delete this job record?"
                                class="inline-flex items-center px-2.5 py-1 rounded text-xs font-medium bg-rose-600 text-white hover:bg-rose-700" wire:loading.attr="disabled">
                                Delete
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center text-slate-500">No failed jobs. 🎉</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="mt-4">
            {{ $this->jobs->links() }}
        </div>
    </div>
</div>
