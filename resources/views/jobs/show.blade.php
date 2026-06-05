@extends('cauce::layouts.app', ['title' => 'Cauce — Job Detail'])

@section('content')
    <div class="cauce-card">
        <h2 class="text-lg font-semibold text-slate-900">Job {{ $id ?? 'n/a' }}</h2>
        <p class="mt-2 text-sm text-slate-600">Detail page — coming in a follow-up release.</p>
    </div>
@endsection
