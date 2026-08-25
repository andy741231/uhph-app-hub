@extends('layouts.app')

@section('title', 'Applications')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">Your workspace</p>
        <h1>Applications</h1>
        <p>Welcome, {{ auth()->user()->name }}.</p>
    </div>
    @if (auth()->user()->is_admin)
        <a class="button button-secondary" href="{{ route('admin.applications.index') }}">Manage applications</a>
    @endif
</div>

@if ($applications->isEmpty())
    <div class="card empty">
        <div class="empty-mark" aria-hidden="true">A</div>
        <h2>No applications assigned yet</h2>
        <p>Your account is active. Applications will appear here after an administrator grants you access.</p>
    </div>
@else
    <div class="launcher">
        @foreach ($applications as $application)
            <a class="launcher-tile" href="{{ $application->launchUrl() }}" title="Open {{ $application->name }}">
                <span class="app-icon {{ $application->iconColorClass() }}" role="img" aria-label="{{ $application->name }} icon">
                    <span class="app-icon-letter" aria-hidden="true">{{ $application->iconInitial() }}</span>
                    <img class="app-icon-image" src="{{ $application->iconUrl() }}" alt="" loading="lazy" onerror="this.remove()">
                </span>
                <span class="launcher-name">{{ $application->name }}</span>
                <span class="launcher-role">{{ $application->pivot->role ? Illuminate\Support\Str::headline($application->pivot->role) : 'Access granted' }}</span>
            </a>
        @endforeach
    </div>
@endif
@endsection
