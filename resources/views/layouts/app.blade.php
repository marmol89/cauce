<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Cauce')</title>
    @livewireStyles
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"
        integrity="sha384-e6nUZLBkQ86NJ6TVVKAeSaK8jWa3NhkYWZFomE39AvDbQWeie9PlQqM3pmYW5d1g"
        crossorigin="anonymous" defer></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"
        integrity="sha384-X9kJyAubVxnP0hcA+AMMs21U445qsnqhnUF8EBlEpP3a42Kh/JwWjlv2ZcvGfphb"
        crossorigin="anonymous"></script>
    @stack('head')
    <style type="text/tailwindcss">
        .cauce-nav-link { @apply px-3 py-2 rounded text-sm font-medium text-slate-600 hover:bg-slate-100; }
        .cauce-nav-link.active { @apply bg-slate-900 text-white hover:bg-slate-800; }
        .cauce-card { @apply bg-white rounded-lg shadow p-5; }
        .cauce-badge { @apply inline-flex items-center px-2 py-0.5 rounded text-xs font-medium; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-14">
            <div class="flex items-center space-x-6">
                <a href="{{ route('cauce.dashboard') }}" class="flex items-center space-x-2">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                    <span class="font-bold text-slate-900">Cauce</span>
                </a>
                <div class="hidden sm:flex items-center space-x-1">
                    <a href="{{ route('cauce.dashboard') }}" class="cauce-nav-link {{ request()->routeIs('cauce.dashboard') ? 'active' : '' }}">Overview</a>
                    <a href="{{ route('cauce.jobs') }}" class="cauce-nav-link {{ request()->routeIs('cauce.jobs*') ? 'active' : '' }}">Jobs</a>
                    <a href="{{ route('cauce.failed') }}" class="cauce-nav-link {{ request()->routeIs('cauce.failed') ? 'active' : '' }}">Failed</a>
                    <a href="{{ route('cauce.metrics') }}" class="cauce-nav-link {{ request()->routeIs('cauce.metrics') ? 'active' : '' }}">Metrics</a>
                    <a href="{{ route('cauce.queues') }}" class="cauce-nav-link {{ request()->routeIs('cauce.queues') ? 'active' : '' }}">Queues</a>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <span class="text-xs text-slate-400">v{{ \Marmol89\Cauce\Facades\Cauce::version() }}</span>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @yield('content')
    </main>

    @livewireScripts
    @stack('scripts')
</body>
</html>
