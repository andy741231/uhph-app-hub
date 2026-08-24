@extends('layouts.app')

@section('title', 'Manage '.$application->name)

@section('content')
<div class="page-heading">
    <div><p class="eyebrow">Application administration</p><h1>{{ $application->name }}</h1><p>{{ $application->path }}</p></div>
    <a href="{{ route('admin.applications.index') }}">Back to applications</a>
</div>
@include('partials.messages')
<form class="card panel" method="POST" action="{{ route('admin.applications.update', $application) }}">
    @csrf
    @method('PUT')
    @include('admin.applications.form')
    <div class="actions"><button class="button button-secondary" type="submit">Save application</button></div>
</form>

<div class="card panel">
    <h2>SSO client credentials</h2>
    @if (session('client_secret'))
        <div class="alert alert-success" role="status">
            <strong>Copy this client secret now. It will not be shown again.</strong>
            <div class="secret-value">{{ session('client_secret') }}</div>
        </div>
    @endif
    <dl class="credential-list">
        <div><dt>Client ID</dt><dd>{{ $application->client_id ?: 'Not generated' }}</dd></div>
        <div><dt>Callback</dt><dd>{{ $application->callback_url ?: 'Not configured' }}</dd></div>
    </dl>
    <form method="POST" action="{{ route('admin.applications.credentials.store', $application) }}">
        @csrf
        <button class="button button-secondary" type="submit">{{ $application->client_id ? 'Rotate credentials' : 'Generate credentials' }}</button>
    </form>
    <p class="hint">Rotating credentials immediately invalidates all outstanding authorization codes for this application.</p>
</div>
@endsection
