<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <title>@yield('title', 'Dashboard') — UH Grants Portal</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-uh-bg text-uh-fg min-h-screen">
    <div class="flex min-h-screen">
        {{-- Sidebar --}}
        <aside class="w-64 bg-uh-red text-white flex-shrink-0 hidden md:flex flex-col">
            <div class="px-5 py-5 border-b border-white/15">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-white/15 flex items-center justify-center p-1.5" aria-hidden="true">
                        <x-heroicon-o-trophy class="w-6 h-6 text-white" />
                    </div>
                    <div>
                        <div class="font-bold text-base leading-tight tracking-wide">Grants Portal</div>
                        <div class="text-xs text-white/80 font-medium">University of Houston</div>
                    </div>
                </div>
            </div>

            <nav class="flex-1 px-3 py-4 space-y-1" aria-label="Main navigation">
                <a href="{{ route('admin.dashboard') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium transition-colors duration-150
                          {{ request()->routeIs('admin.dashboard') ? 'bg-white/20 text-white font-semibold' : 'text-white/85 hover:text-white hover:bg-white/10' }}">
                    <x-heroicon-o-home class="w-5 h-5" />
                    Dashboard
                </a>
                <a href="{{ route('admin.rounds.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium transition-colors duration-150
                          {{ request()->routeIs('admin.rounds.*') ? 'bg-white/20 text-white font-semibold' : 'text-white/85 hover:text-white hover:bg-white/10' }}">
                    <x-heroicon-o-calendar class="w-5 h-5" />
                    Rounds
                </a>
                <a href="{{ route('admin.users.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium transition-colors duration-150
                          {{ request()->routeIs('admin.users.*') ? 'bg-white/20 text-white font-semibold' : 'text-white/85 hover:text-white hover:bg-white/10' }}">
                    <x-heroicon-o-users class="w-5 h-5" />
                    Users
                </a>
                <a href="{{ route('admin.review-assignments.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium transition-colors duration-150
                          {{ request()->routeIs('admin.review-assignments.*') ? 'bg-white/20 text-white font-semibold' : 'text-white/85 hover:text-white hover:bg-white/10' }}">
                    <x-heroicon-o-user-plus class="w-5 h-5" />
                    Assign reviewers
                </a>
                <a href="{{ route('admin.review-results.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium transition-colors duration-150
                          {{ request()->routeIs('admin.review-results.*') ? 'bg-white/20 text-white font-semibold' : 'text-white/85 hover:text-white hover:bg-white/10' }}">
                    <x-heroicon-o-chart-bar class="w-5 h-5" />
                    Review results
                </a>
            </nav>

            <div class="px-3 py-4 border-t border-white/15">
                <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-white/10 transition-colors cursor-pointer">
                    <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center text-sm font-bold text-white">
                        {{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold text-white truncate">{{ auth()->user()->full_name }}</div>
                        <div class="text-xs text-white/75 truncate">{{ auth()->user()->email }}</div>
                    </div>
                </a>
                <form method="POST" action="{{ route('logout') }}" class="mt-2">
                    @csrf
                    <button type="submit" class="flex items-center gap-2 w-full px-3 py-2 rounded-md text-sm text-white/80 hover:text-white hover:bg-white/10 transition-colors duration-150">
                        <x-heroicon-o-arrow-left-on-rectangle class="w-4 h-4" />
                        Sign out
                    </button>
                </form>
            </div>
        </aside>

        {{-- Main content --}}
        <div class="flex-1 flex flex-col min-w-0">
            {{-- Mobile header --}}
            <header class="md:hidden bg-uh-red text-white px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-trophy class="w-6 h-6 text-white" />
                    <span class="font-bold">Grants Portal</span>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-white/80 hover:text-white">Sign out</button>
                </form>
            </header>

            {{-- Mobile nav --}}
            <nav class="md:hidden bg-white border-b border-uh-border px-4 py-2 flex gap-4 text-sm" aria-label="Mobile navigation">
                <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'text-uh-red font-semibold' : 'text-gray-600' }}">Dashboard</a>
                <a href="{{ route('admin.rounds.index') }}" class="{{ request()->routeIs('admin.rounds.*') ? 'text-uh-red font-semibold' : 'text-gray-600' }}">Rounds</a>
                <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'text-uh-red font-semibold' : 'text-gray-600' }}">Users</a>
                <a href="{{ route('admin.review-assignments.index') }}" class="{{ request()->routeIs('admin.review-assignments.*') ? 'text-uh-red font-semibold' : 'text-gray-600' }}">Assignments</a>
                <a href="{{ route('admin.review-results.index') }}" class="{{ request()->routeIs('admin.review-results.*') ? 'text-uh-red font-semibold' : 'text-gray-600' }}">Results</a>
            </nav>

            {{-- Page content --}}
            <main class="flex-1 p-6 md:p-8 max-w-7xl mx-auto w-full">
                @if (session('status'))
                    <div role="alert" class="mb-6 flex items-start gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                        <span class="text-sm">{{ session('status') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div role="alert" class="mb-6 flex items-start gap-3 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                        </svg>
                        <div class="text-sm">
                            <ul class="list-disc pl-4 space-y-0.5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
