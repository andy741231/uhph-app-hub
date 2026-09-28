@extends('layouts.admin')
@section('title', 'Email Preview')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-uh-fg">Email preview</h1>
        <p class="text-sm text-gray-500 mt-1">
            {{ $label }} — subject: <span class="font-medium text-uh-fg">{{ $subject }}</span>
        </p>
    </div>
    <a href="{{ route('admin.workflow') }}" class="btn-secondary">Back to Workflow Chart</a>
</div>

<div class="card overflow-hidden">
    <iframe src="{{ route('admin.email-preview.raw', $key) }}" title="Rendered email: {{ $subject }}" class="w-full border-0" style="min-height: 78vh"></iframe>
</div>
@endsection
