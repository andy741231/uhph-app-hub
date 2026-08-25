<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <title>@yield('title', 'My Submissions') — UH Grants Portal</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-uh-bg text-uh-fg min-h-screen">
    <header class="bg-uh-red text-white shadow-md">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between">
            <a href="{{ route('submitter.submissions.index') }}" class="flex items-center gap-3 cursor-pointer">
                <div class="w-9 h-9 rounded-lg bg-white/15 flex items-center justify-center p-1.5" aria-hidden="true">
                    <x-heroicon-o-trophy class="w-6 h-6 text-white" />
                </div>
                <div>
                    <div class="font-bold text-lg leading-tight tracking-wide">UH Grants Portal</div>
                    <div class="text-xs text-white/80 font-medium">Submitter workspace</div>
                </div>
            </a>
            <div class="flex items-center gap-6">
                <nav class="hidden sm:flex items-center gap-5 text-sm" aria-label="Submitter navigation">
                    <a href="{{ route('submitter.submissions.index') }}"
                       class="font-medium transition-colors duration-150 {{ request()->routeIs('submitter.submissions.*') ? 'text-white font-semibold' : 'text-white/80 hover:text-white' }}">
                        My submissions
                    </a>
                </nav>
                <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
                    <button @click="open = !open" class="flex items-center gap-2 text-sm font-medium text-white/85 hover:text-white transition-colors cursor-pointer">
                        <span class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center text-xs font-bold text-white">
                            {{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}
                        </span>
                        <span class="hidden sm:inline">{{ auth()->user()->full_name }}</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                        </svg>
                    </button>
                    <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-uh-border py-1 z-50" style="display: none;">
                        @if (config('hub.enabled') && session('hub_application_count', 1) > 1)
                            <a href="{{ config('hub.base_url') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-uh-muted transition-colors">
                                <x-heroicon-o-squares-2x2 class="w-4 h-4 text-gray-400" />
                                All applications
                            </a>
                        @endif
                        <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-uh-muted transition-colors">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                            </svg>
                            My Profile
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-uh-muted transition-colors cursor-pointer">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75"/>
                                </svg>
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    {{-- Mobile nav --}}
    <nav class="sm:hidden bg-white border-b border-uh-border px-4 py-2 flex gap-4 text-sm" aria-label="Mobile navigation">
        <a href="{{ route('submitter.submissions.index') }}" class="{{ request()->routeIs('submitter.submissions.*') ? 'text-uh-red font-semibold' : 'text-gray-600' }}">My submissions</a>
        <a href="{{ route('profile.edit') }}" class="{{ request()->routeIs('profile.*') ? 'text-uh-red font-semibold' : 'text-gray-600' }}">Profile</a>
    </nav>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 py-8">
        @if (session('status'))
            <div role="alert" class="mb-6 flex items-start gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 12.75 2.25 2.25L15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
                <span class="text-sm">{{ session('status') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                <ul class="list-disc pl-5 text-sm space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
