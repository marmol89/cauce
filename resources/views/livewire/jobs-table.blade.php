<div>
    <div class="cauce-card mb-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by class..."
                class="rounded border-slate-200 text-sm focus:ring-indigo-500 focus:border-indigo-500">
            <select wire:model.live="connection" class="rounded border-slate-200 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">All connections</option>
                @foreach($this->connections as $conn)
                    <option value="{{ $conn }}">{{ $conn }}</option>
                @endforeach
            </select>
            <select wire:model.live="queue" class="rounded border-slate-200 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">All queues</option>
                @foreach($this->queues as $q)
                    <option value="{{ $q }}">{{ $q }}</option>
                @endforeach
            </select>
            <select wire:model.live="status" class="rounded border-slate-200 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">All statuses</option>
                <option value="queued">Queued</option>
                <option value="processing">Processing</option>
                <option value="completed">Completed</option>
                <option value="retrying">Retrying</option>
                <option value="failed">Failed</option>
            </select>
            <select wire:model.live="tag" class="rounded border-slate-200 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">All tags</option>
                @foreach($this->availableTags as $t)
                    <option value="{{ $t }}">{{ $t }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="cauce-card overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 uppercase text-xs">
                    <th class="px-3 py-2">Job</th>
                    <th class="px-3 py-2">Connection</th>
                    <th class="px-3 py-2">Queue</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Attempts</th>
                    <th class="px-3 py-2">Runtime</th>
                    <th class="px-3 py-2">Finished</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($this->jobs as $job)
                    <tr>
                        <td class="px-3 py-2 font-mono text-xs text-slate-700">
                            <a href="{{ route('cauce.jobs.show', $job->id) }}" class="hover:text-indigo-600">
                                {{ \Illuminate\Support\Str::limit($job->name, 40) }}
                            </a>
                        </td>
                        <td class="px-3 py-2 text-slate-600">{{ $job->connection }}</td>
                        <td class="px-3 py-2 text-slate-600">{{ $job->queue }}</td>
                        <td class="px-3 py-2">
                            <span class="cauce-badge {{ match($job->status) {
                                'completed' => 'bg-emerald-100 text-emerald-800',
                                'failed' => 'bg-rose-100 text-rose-800',
                                'processing' => 'bg-amber-100 text-amber-800',
                                'retrying' => 'bg-orange-100 text-orange-800',
                                default => 'bg-slate-100 text-slate-800',
                            } }}">{{ $job->status }}</span>
                        </td>
                        <td class="px-3 py-2 text-slate-600">{{ $job->attempts }}</td>
                        <td class="px-3 py-2 text-slate-600">
                            {{ $job->runtime_ms !== null ? $job->runtime_ms . ' ms' : '—' }}
                        </td>
                        <td class="px-3 py-2 text-slate-500 text-xs">{{ $job->finished_at?->diffForHumans() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-6 text-center text-slate-500">No jobs tracked yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="mt-4">
            {{ $this->jobs->links() }}
        </div>
    </div>
</div>
