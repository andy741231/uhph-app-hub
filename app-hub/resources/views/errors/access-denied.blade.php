@extends('layouts.app')

@section('title', 'Access denied')

@section('content')
<div class="card empty">
    <div class="empty-mark" aria-hidden="true">!</div>
    <h1>Access denied</h1>
    <p>You do not currently have access to {{ $application->name }}. Contact an App Hub administrator if you believe this is incorrect.</p>
    <p class="empty-action"><a class="button button-secondary" href="{{ route('dashboard') }}">Return to applications</a></p>
</div>
@endsection
