@extends('layouts.app')

@section('title', 'Manage '.$managedUser->name)

@section('content')
<div class="page-heading">
    <div><p class="eyebrow">User administration</p><h1>{{ $managedUser->name }}</h1><p>{{ $managedUser->email }}</p></div>
    <a href="{{ route('admin.users.index') }}">Back to users</a>
</div>

@include('partials.messages')

<form class="card panel" method="POST" action="{{ route('admin.users.update', $managedUser) }}">
    @csrf
    @method('PUT')
    <h2>Account details</h2>
    @include('admin.users.form')
    <div class="actions"><button class="button button-secondary" type="submit">Save account</button></div>
</form>

<form class="card panel" method="POST" action="{{ route('admin.users.applications.update', $managedUser) }}">
    @csrf
    @method('PUT')
    <h2>Application access</h2>
    @forelse ($applications as $application)
        @php($assignment = $managedUser->applications->firstWhere('id', $application->id))
        <div class="assignment">
            <div>
                <label class="check" for="application-{{ $application->id }}">
                    <input id="application-{{ $application->id }}" name="applications[{{ $application->id }}][enabled]" type="checkbox" value="1" @checked(old("applications.{$application->id}.enabled", $assignment !== null))>
                    <span class="assignment-title">{{ $application->name }}</span>
                </label>
                <div class="assignment-path">{{ $application->path }} @if (! $application->enabled) · Disabled @endif</div>
            </div>
            <div>
                @if (($application->roles ?? []) !== [])
                    <label class="label" for="application-role-{{ $application->id }}">Role</label>
                    <select class="input" id="application-role-{{ $application->id }}" name="applications[{{ $application->id }}][role]">
                        <option value="">Select a role</option>
                        @foreach ($application->roles as $role)
                            <option value="{{ $role }}" @selected(old("applications.{$application->id}.role", $assignment?->pivot->role) === $role)>{{ Illuminate\Support\Str::headline($role) }}</option>
                        @endforeach
                    </select>
                    @error("applications.{$application->id}.role")<p class="field-error">{{ $message }}</p>@enderror
                @else
                    <span class="hint">No application role required</span>
                @endif
            </div>
        </div>
    @empty
        <p class="hint">Register an application before assigning access.</p>
    @endforelse
    <div class="actions" style="margin-top: 22px;"><button class="button button-secondary" type="submit">Save application access</button></div>
</form>

@if (auth()->id() !== $managedUser->id)
<form class="card panel danger-zone" method="POST" action="{{ route('admin.users.destroy', $managedUser) }}" onsubmit="return confirm('Delete {{ addslashes($managedUser->name) }}? This removes their Hub account, application access, and pending SSO codes. Audit history is retained. This cannot be undone.');">
    @csrf
    @method('DELETE')
    <h2>Delete user</h2>
    <p class="hint">Permanently removes the Hub account, application assignments, and pending SSO authorization codes. Login and launch audit history is retained with the user anonymized. This action cannot be undone.</p>
    <div class="actions"><button class="button button-danger" type="submit">Delete {{ $managedUser->name }}</button></div>
</form>
@else
<div class="card panel danger-zone" aria-hidden="false">
    <h2>Delete user</h2>
    <p class="hint">You cannot delete your own account. Ask another Hub administrator to remove it.</p>
</div>
@endif
@endsection
