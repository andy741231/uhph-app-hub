@extends('layouts.app')

@section('title', 'Import users')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">User administration</p>
        <h1>Import users</h1>
        <p>Create Hub accounts and assign application roles from a CSV file.</p>
    </div>
    <div class="actions">
        <a class="button button-secondary" href="{{ route('admin.users.import.template') }}">Download example CSV</a>
        <a href="{{ route('admin.users.index') }}">Back to users</a>
    </div>
</div>

@include('partials.messages')

<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); align-items: start;">
    <form class="card panel" method="POST" action="{{ route('admin.users.import.store') }}" enctype="multipart/form-data">
        @csrf
        <h2>Upload CSV</h2>
        <div class="field">
            <label class="label" for="csv">CSV file</label>
            <input class="input" id="csv" name="csv" type="file" accept=".csv,.txt,text/csv" required @error('csv') aria-invalid="true" aria-describedby="csv-error" @enderror>
            <p class="hint">Maximum 2 MB and 1,000 rows. The entire file is validated before any accounts or assignments are changed.</p>
            @error('csv')<p class="field-error" id="csv-error">{{ $message }}</p>@enderror
        </div>
        <button class="button button-primary" type="submit">Validate and import users</button>
    </form>

    <div class="card panel">
        <h2>Required format</h2>
        <p class="hint">Use these columns in this exact order:</p>
        <p class="secret-value">name,email,application,role</p>
        <ul class="hint" style="padding-left: 20px; line-height: 1.8;">
            <li>Use institutional email addresses only.</li>
            <li>Application values use the keys shown below.</li>
            <li>Existing accounts keep their password and Hub permissions.</li>
            <li>New users receive a secure set-password email.</li>
            <li>CSV imports can never grant UHPH App Hub administrator access.</li>
        </ul>
    </div>
</div>

<div class="card table-wrap" style="margin-top: 24px;">
    <table>
        <thead><tr><th scope="col">Application key</th><th scope="col">Application</th><th scope="col">Allowed roles</th></tr></thead>
        <tbody>
            @foreach ($applications as $application)
                <tr>
                    <td><code>{{ $application->key }}</code></td>
                    <td>{{ $application->name }}</td>
                    <td>{{ implode(', ', $application->roles ?? []) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
