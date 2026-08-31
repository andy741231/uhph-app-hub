@extends('layouts.app')

@section('title', 'Create user')

@section('content')
<div class="page-heading">
    <div><p class="eyebrow">User administration</p><h1>Create user</h1><p>Create an administrator-managed UHPH App Hub account.</p></div>
    <a href="{{ route('admin.users.index') }}">Back to users</a>
</div>

@include('partials.messages')

<form class="card panel" method="POST" action="{{ route('admin.users.store') }}">
    @csrf
    @include('admin.users.form')
    <div class="actions"><button class="button button-secondary" type="submit">Create user</button></div>
</form>
@endsection
