@extends('cauce::layouts.app', ['title' => 'Cauce — Overview'])

@section('content')
    <div wire:poll.{{ config('cauce.dashboard.refresh_seconds', 5) }}s="dispatch('cauce:refresh')">
        @livewire('cauce-dashboard')
    </div>
@endsection
