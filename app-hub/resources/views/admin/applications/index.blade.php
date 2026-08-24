@extends('layouts.app')

@section('title', 'Manage applications')

@section('content')
<div class="page-heading">
    <div><p class="eyebrow">Administration</p><h1>Applications</h1><p>Register application destinations and supported roles.</p></div>
    <a class="button button-secondary" href="{{ route('admin.applications.create') }}">Register application</a>
</div>

@include('partials.messages')

<div class="card table-wrap">
    <table>
        <thead><tr><th scope="col">Application</th><th scope="col">Status</th><th scope="col">Users</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
        <tbody>
            @forelse ($applications as $application)
                <tr>
                    <td><a class="table-link" href="{{ route('admin.applications.edit', $application) }}">{{ $application->name }}</a><div class="hint">{{ $application->path }}</div></td>
                    <td><span class="badge {{ $application->enabled ? '' : 'badge-muted' }}">{{ $application->enabled ? 'Enabled' : 'Disabled' }}</span></td>
                    <td>{{ $application->users_count }}</td>
                    <td><a href="{{ route('admin.applications.edit', $application) }}">Manage</a></td>
                </tr>
            @empty
                <tr><td colspan="4">No applications have been registered.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
