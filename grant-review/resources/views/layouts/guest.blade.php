<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <title>@yield('title', 'Pilot Central') — Pilot Central</title>
    @include('layouts.partials.fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-uh-bg text-uh-fg min-h-screen flex flex-col items-center justify-center px-4">
    <div class="mb-8 flex items-center gap-3">
        <div class="w-11 h-11 bg-uh-red rounded-xl flex items-center justify-center shadow-md p-2">
            <x-heroicon-o-trophy class="w-7 h-7 text-white" />
        </div>
        <div>
            <div class="font-bold text-xl text-uh-fg tracking-tight">Pilot Central</div>
            <div class="text-xs text-uh-slate font-medium">University of Houston</div>
        </div>
    </div>

    @if (session('error'))
        <div role="alert" class="mb-6 w-full max-w-md bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">
            {{ session('error') }}
        </div>
    @endif

    @isset($slot)
        {{ $slot }}
    @endisset
    @yield('content')
</body>
</html>
