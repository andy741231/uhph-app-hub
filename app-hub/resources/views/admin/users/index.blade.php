@extends('layouts.app')

@section('title', 'Manage users')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">Administration</p>
        <h1>Users</h1>
        <p>Create accounts and manage application access.</p>
    </div>
    <div class="actions">
        <a class="button button-secondary" href="{{ route('admin.users.import.create') }}">Import users</a>
        <a class="button button-secondary" href="{{ route('admin.users.create') }}">Create user</a>
    </div>
</div>

@include('partials.messages')

<form id="bulk-users-form" method="POST" action="{{ route('admin.users.bulk.destroy') }}" onsubmit="return confirm('Delete the selected user(s)? This removes their Hub accounts, application access, and pending SSO codes. Audit history is retained. This cannot be undone.');">
    @csrf
    @method('DELETE')
    <div class="bulk-bar">
        <span class="hint" id="bulk-count" aria-live="polite">0 selected</span>
        <button class="button button-danger button-compact" type="submit" id="bulk-delete-btn" disabled>Delete selected</button>
    </div>
    <div class="card table-wrap">
        <table>
            <thead>
                <tr>
                    <th scope="col" style="width: 36px;"><input type="checkbox" id="select-all-users" aria-label="Select all users"></th>
                    <th scope="col">User</th>
                    <th scope="col">Status</th>
                    <th scope="col">Applications</th>
                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>
                            @if (auth()->id() === $user->id)
                                <span class="visually-hidden">Cannot delete yourself</span>
                            @else
                                <input type="checkbox" name="users[]" value="{{ $user->id }}" class="user-row-check" aria-label="Select {{ $user->name }}">
                            @endif
                        </td>
                        <td><a class="table-link" href="{{ route('admin.users.edit', $user) }}">{{ $user->name }}</a><div class="hint">{{ $user->email }}</div></td>
                        <td><span class="badge {{ $user->isActive() ? '' : 'badge-muted' }}">{{ Illuminate\Support\Str::headline($user->status) }}</span>@if ($user->is_admin) <span class="badge">Administrator</span>@endif</td>
                        <td>{{ $user->applications_count }}</td>
                        <td><a href="{{ route('admin.users.edit', $user) }}">Manage</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5">No users have been created.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</form>

@if ($users->hasPages())
    <div class="actions" style="margin-top: 20px;">
        @if ($users->previousPageUrl())<a class="button button-secondary button-compact" href="{{ $users->previousPageUrl() }}">Previous</a>@endif
        @if ($users->nextPageUrl())<a class="button button-secondary button-compact" href="{{ $users->nextPageUrl() }}">Next</a>@endif
    </div>
@endif

<script>
(function () {
    var form = document.getElementById('bulk-users-form');
    if (!form) return;
    var selectAll = document.getElementById('select-all-users');
    var rowChecks = Array.prototype.slice.call(form.querySelectorAll('.user-row-check'));
    var countEl = document.getElementById('bulk-count');
    var deleteBtn = document.getElementById('bulk-delete-btn');

    function update() {
        var checked = rowChecks.filter(function (c) { return c.checked; }).length;
        countEl.textContent = checked + ' selected';
        deleteBtn.disabled = checked === 0;
        if (rowChecks.length > 0) {
            selectAll.checked = checked === rowChecks.length;
            selectAll.indeterminate = checked > 0 && checked < rowChecks.length;
        }
    }

    selectAll.addEventListener('change', function () {
        rowChecks.forEach(function (c) { c.checked = selectAll.checked; });
        update();
    });
    rowChecks.forEach(function (c) { c.addEventListener('change', update); });
    update();
})();
</script>
@endsection
