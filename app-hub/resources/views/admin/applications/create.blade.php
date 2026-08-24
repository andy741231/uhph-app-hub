@extends('layouts.app')

@section('title', 'Register application')

@section('content')
<div class="page-heading">
    <div><p class="eyebrow">Application administration</p><h1>Register application</h1><p>Add an internal application to the Hub registry.</p></div>
    <a href="{{ route('admin.applications.index') }}">Back to applications</a>
</div>
@include('partials.messages')
<form class="card panel" method="POST" action="{{ route('admin.applications.store') }}">
    @csrf
    @include('admin.applications.form')
    <div class="actions"><button class="button button-secondary" type="submit">Register application</button></div>
</form>
@endsection
