@php
    $statusColors = [
        'queued'     => 'bg-slate-100 text-slate-700',
        'processing' => 'bg-amber-100 text-amber-800',
        'completed'  => 'bg-emerald-100 text-emerald-800',
        'failed'     => 'bg-rose-100 text-rose-800',
        'retrying'   => 'bg-orange-100 text-orange-800',
    ];
    $badge = $statusColors[$job->status] ?? 'bg-slate-100 text-slate-700';

    $payload = $job->payload;
    if (is_string($payload)) {
        $payload = json_decode($payload, true) ?: null;
    }

    $tags = $job->tags;
    if (is_string($tags)) {
        $tags = json_decode($tags, true) ?: [];
    }

    $fmt = fn ($value) => $value instanceof \DateTimeInterface
        ? $value->format('Y-m-d H:i:s')
        : ($value ?: '—');

    $diff = fn ($value) => $value instanceof \DateTimeInterface ? $value->diffForHumans() : null;
@endphp

@extends('cauce::layouts.app', ['title' => 'Cauce — Job ' . $job->id])

@section('content')
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('cauce.jobs') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Jobs</a>
                    <span class="cauce-badge {{ $badge }}">{{ strtoupper($job->status) }}</span>
                </div>
                <h1 class="mt-2 text-xl font-semibold text-slate-900 break-all">{{ $job->name }}</h1>
                <p class="mt-1 text-xs text-slate-500 font-mono">{{ $job->id }}</p>
            </div>
            <div class="flex gap-2">
                @if ($job->status === 'failed')
                    <form method="POST" action="{{ route('cauce.jobs.action', $job->id) }}" onsubmit="return confirm('Re-queue this job?')">
                        @csrf
                        <input type="hidden" name="_action" value="retry">
                        <button class="px-3 py-1.5 rounded text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-700">Retry</button>
                    </form>
                    <form method="POST" action="{{ route('cauce.jobs.action', $job->id) }}" onsubmit="return confirm('Delete this job record?')">
                        @csrf
                        <input type="hidden" name="_action" value="delete">
                        <button class="px-3 py-1.5 rounded text-sm font-medium bg-rose-600 text-white hover:bg-rose-700">Delete</button>
                    </form>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="rounded bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-2 text-sm">
                {{ session('status') }}
            </div>
        @endif

        {{-- Quick facts grid --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="cauce-card">
                <p class="text-xs uppercase tracking-wide text-slate-500">Connection</p>
                <p class="mt-1 text-sm font-medium text-slate-900 break-all">{{ $job->connection ?? '—' }}</p>
            </div>
            <div class="cauce-card">
                <p class="text-xs uppercase tracking-wide text-slate-500">Queue</p>
                <p class="mt-1 text-sm font-medium text-slate-900 break-all">{{ $job->queue ?? '—' }}</p>
            </div>
            <div class="cauce-card">
                <p class="text-xs uppercase tracking-wide text-slate-500">Attempts</p>
                <p class="mt-1 text-sm font-medium text-slate-900">{{ $job->attempts ?? 0 }}</p>
            </div>
            <div class="cauce-card">
                <p class="text-xs uppercase tracking-wide text-slate-500">Runtime</p>
                <p class="mt-1 text-sm font-medium text-slate-900">{{ $job->runtime_ms !== null ? $job->runtime_ms . ' ms' : '—' }}</p>
            </div>
        </div>

        {{-- Identifiers + timeline --}}
        <div class="grid md:grid-cols-2 gap-6">
            <div class="cauce-card">
                <h2 class="text-sm font-semibold text-slate-700 mb-3">Identifiers</h2>
                <dl class="text-sm divide-y divide-slate-100">
                    <div class="grid grid-cols-3 py-2">
                        <dt class="text-slate-500">Cauce ID</dt>
                        <dd class="col-span-2 font-mono text-xs text-slate-800 break-all">{{ $job->id }}</dd>
                    </div>
                    <div class="grid grid-cols-3 py-2">
                        <dt class="text-slate-500">Job UUID</dt>
                        <dd class="col-span-2 font-mono text-xs text-slate-800 break-all">{{ $job->uuid ?? '—' }}</dd>
                    </div>
                    <div class="grid grid-cols-3 py-2">
                        <dt class="text-slate-500">Display name</dt>
                        <dd class="col-span-2 text-slate-800 break-all">{{ $payload['displayName'] ?? $job->name }}</dd>
                    </div>
                    <div class="grid grid-cols-3 py-2">
                        <dt class="text-slate-500">Handler</dt>
                        <dd class="col-span-2 text-slate-800 break-all">{{ $payload['job'] ?? '—' }}</dd>
                    </div>
                    <div class="grid grid-cols-3 py-2">
                        <dt class="text-slate-500">Max tries</dt>
                        <dd class="col-span-2 text-slate-800">{{ $payload['maxTries'] ?? '—' }}</dd>
                    </div>
                    <div class="grid grid-cols-3 py-2">
                        <dt class="text-slate-500">Timeout</dt>
                        <dd class="col-span-2 text-slate-800">{{ $payload['timeout'] ?? '—' }}</dd>
                    </div>
                    <div class="grid grid-cols-3 py-2">
                        <dt class="text-slate-500">Backoff</dt>
                        <dd class="col-span-2 text-slate-800">{{ is_array($payload['backoff'] ?? null) ? implode(', ', $payload['backoff']) : ($payload['backoff'] ?? '—') }}</dd>
                    </div>
                </dl>
            </div>

            <div class="cauce-card">
                <h2 class="text-sm font-semibold text-slate-700 mb-3">Timeline</h2>
                <ol class="relative border-l border-slate-200 ml-2 space-y-4">
                    @foreach ([
                        ['Queued', $job->queued_at ?? $job->created_at],
                        ['Started', $job->started_at],
                        ['Finished', $job->finished_at],
                        ['Failed', $job->failed_at],
                        ['Updated', $job->updated_at],
                    ] as [$label, $value])
                        <li class="ml-4">
                            <span class="absolute -left-1.5 mt-1.5 w-3 h-3 rounded-full {{ $value ? 'bg-indigo-500' : 'bg-slate-200' }}"></span>
                            <p class="text-xs uppercase tracking-wide text-slate-500">{{ $label }}</p>
                            <p class="text-sm text-slate-800">{{ $fmt($value) }}</p>
                            @if ($value && $diff($value))
                                <p class="text-xs text-slate-400">{{ $diff($value) }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>

        {{-- Tags --}}
        @if (! empty($tags))
            <div class="cauce-card">
                <h2 class="text-sm font-semibold text-slate-700 mb-3">Tags</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach ($tags as $tag)
                        <span class="cauce-badge bg-slate-100 text-slate-700">{{ $tag }}</span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Exception --}}
        @if (! empty($job->exception))
            <div class="cauce-card border-l-4 border-rose-500">
                <h2 class="text-sm font-semibold text-rose-700 mb-3">Exception</h2>
                <pre class="text-xs text-slate-800 whitespace-pre-wrap break-all bg-slate-50 p-3 rounded max-h-96 overflow-auto">{{ $job->exception }}</pre>
            </div>
        @endif

        {{-- Payload --}}
        <div class="cauce-card">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-slate-700">Raw payload</h2>
                <span class="text-xs text-slate-400">{{ $payload ? count($payload) . ' keys' : 'no payload stored' }}</span>
            </div>
            @if ($payload)
                <pre class="text-xs text-slate-800 whitespace-pre-wrap break-all bg-slate-50 p-3 rounded max-h-[32rem] overflow-auto">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            @else
                <p class="text-sm text-slate-500">No payload stored for this job.</p>
            @endif
        </div>
    </div>
@endsection
