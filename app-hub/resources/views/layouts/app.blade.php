<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <title>@yield('title', 'App Hub') | {{ config('app.name') }}</title>
    <style>
        :root {
            color-scheme: light;
            --red: #c8102e;
            --red-dark: #960c22;
            --cream: #fff9d9;
            --gold: #f6be00;
            --slate: #54585a;
            --ink: #222426;
            --muted: #666b6e;
            --line: #d8dadd;
            --surface: #ffffff;
            --success-bg: #ecfdf3;
            --success-text: #166534;
            --error-bg: #fff1f2;
            --error-text: #9f1239;
        }
        * { box-sizing: border-box; }
        .visually-hidden { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
        html { font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: var(--ink); background: #f5f5f3; }
        body { min-height: 100vh; margin: 0; }
        button, input { font: inherit; }
        a { color: var(--red); }
        a:hover { color: var(--red-dark); }
        :focus-visible { outline: 3px solid var(--gold); outline-offset: 3px; }
        .shell { min-height: 100vh; display: grid; grid-template-rows: auto 1fr; }
        .topbar { background: var(--surface); border-top: 5px solid var(--red); border-bottom: 1px solid var(--line); }
        .topbar-inner { width: min(1120px, calc(100% - 32px)); min-height: 72px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; gap: 24px; }
        .brand { display: inline-flex; align-items: center; gap: 12px; color: var(--ink); font-weight: 750; text-decoration: none; letter-spacing: -.02em; }
        .brand-mark { width: 38px; height: 38px; display: grid; place-items: center; border-radius: 9px; color: #fff; background: var(--red); font-size: 13px; letter-spacing: .04em; }
        .account { display: flex; align-items: center; gap: 12px; padding: 6px 8px 6px 6px; border: 1px solid var(--line); border-radius: 999px; background: #f7f7f5; }
        .avatar { width: 34px; height: 34px; display: grid; place-items: center; border-radius: 50%; color: #fff; background: var(--red); font-size: 13px; font-weight: 800; letter-spacing: .03em; }
        .account-meta { display: flex; flex-direction: column; align-items: flex-start; gap: 3px; line-height: 1.1; }
        .account-name { color: var(--ink); font-size: 14px; font-weight: 700; }
        .main { width: min(1120px, calc(100% - 32px)); margin: 0 auto; padding: 48px 0 64px; }
        .guest-main { width: min(440px, calc(100% - 32px)); margin: auto; padding: 56px 0; }
        .card { background: var(--surface); border: 1px solid var(--line); border-radius: 16px; box-shadow: 0 18px 50px rgba(34, 36, 38, .08); }
        .login-card { padding: 34px; }
        .eyebrow { margin: 0 0 9px; color: var(--red); font-size: 12px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
        h1 { margin: 0; font-size: clamp(28px, 5vw, 38px); line-height: 1.12; letter-spacing: -.035em; }
        .lede { margin: 12px 0 28px; color: var(--muted); line-height: 1.6; }
        .field { margin-bottom: 20px; }
        .label { display: block; margin-bottom: 8px; font-size: 14px; font-weight: 700; }
        .input { width: 100%; min-height: 48px; padding: 11px 13px; color: var(--ink); background: #fff; border: 1px solid #aeb2b4; border-radius: 8px; }
        .input:hover { border-color: var(--slate); }
        .input[aria-invalid="true"] { border-color: var(--red); }
        .field-error { margin: 7px 0 0; color: var(--error-text); font-size: 13px; }
        .form-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin: 4px 0 24px; }
        .check { display: inline-flex; align-items: center; gap: 9px; color: var(--muted); font-size: 14px; }
        .check input { width: 18px; height: 18px; accent-color: var(--red); }
        .button { min-height: 46px; display: inline-flex; align-items: center; justify-content: center; padding: 10px 18px; border: 0; border-radius: 8px; cursor: pointer; font-weight: 750; text-decoration: none; }
        .button-primary { width: 100%; color: #fff; background: var(--red); }
        .button-primary:hover { color: #fff; background: var(--red-dark); }
        .button-link { min-height: 44px; padding: 8px 4px; color: var(--red); background: transparent; }
        .button-link:hover { color: var(--red-dark); text-decoration: underline; }
        .alert { margin-bottom: 22px; padding: 12px 14px; border-radius: 8px; font-size: 14px; line-height: 1.45; }
        .alert-success { color: var(--success-text); background: var(--success-bg); }
        .alert-error { color: var(--error-text); background: var(--error-bg); }
        .support { margin: 22px 0 0; color: var(--muted); text-align: center; font-size: 13px; line-height: 1.5; }
        .page-heading { display: flex; align-items: end; justify-content: space-between; gap: 24px; margin-bottom: 28px; }
        .page-heading p { margin: 8px 0 0; color: var(--muted); }
        .empty { padding: 56px 32px; text-align: center; }
        .empty-mark { width: 54px; height: 54px; display: grid; place-items: center; margin: 0 auto 18px; border-radius: 14px; color: var(--red); background: var(--cream); font-size: 22px; font-weight: 800; }
        .empty h2 { margin: 0; font-size: 21px; }
        .empty p { max-width: 520px; margin: 10px auto 0; color: var(--muted); line-height: 1.6; }
        .badge { display: inline-flex; padding: 4px 8px; border-radius: 999px; color: var(--red-dark); background: var(--cream); font-size: 12px; font-weight: 750; }
        .badge-muted { color: var(--muted); background: #eceeed; }
        .topbar-actions { display: flex; align-items: center; gap: 28px; }
        .nav { display: flex; align-items: center; gap: 18px; }
        .nav a { min-height: 44px; display: inline-flex; align-items: center; color: var(--muted); font-size: 14px; font-weight: 700; text-decoration: none; }
        .nav a:hover, .nav a[aria-current="page"] { color: var(--red); }
        .button-secondary { color: var(--ink); background: #eceeed; }
        .button-secondary:hover { color: var(--ink); background: #dfe1e2; }
        .button-danger { color: #fff; background: var(--red); }
        .button-danger:hover { color: #fff; background: var(--red-dark); }
        .danger-zone { border-color: #f1c0c8; }
        .danger-zone h2 { color: var(--red-dark); }
        .bulk-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 14px; margin-bottom: 16px; }
        .bulk-bar .hint { margin: 0; }
        .button-compact { min-height: 40px; padding: 8px 14px; font-size: 14px; }
        .actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
        .grid { display: grid; gap: 20px; }
        .app-grid { grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); }
        .app-card { display: flex; min-height: 190px; flex-direction: column; padding: 24px; }
        .app-card h2 { margin: 0; font-size: 21px; }
        .app-card p { color: var(--muted); line-height: 1.5; }
        .app-card .actions { margin-top: auto; }
        .launcher { display: grid; grid-template-columns: repeat(auto-fill, minmax(112px, 1fr)); gap: 34px 16px; justify-items: center; }
        .launcher-tile { display: flex; flex-direction: column; align-items: center; gap: 10px; width: 100%; max-width: 132px; color: var(--ink); text-decoration: none; text-align: center; }
        .app-icon { position: relative; width: 72px; height: 72px; display: grid; place-items: center; overflow: hidden; border-radius: 18px; color: #fff; font-size: 26px; font-weight: 800; letter-spacing: .03em; box-shadow: 0 10px 22px rgba(34, 36, 38, .18), inset 0 0 0 1px rgba(255, 255, 255, .16); transition: transform .15s ease, box-shadow .15s ease; }
        .launcher-tile:hover .app-icon { transform: translateY(-3px) scale(1.04); box-shadow: 0 16px 30px rgba(34, 36, 38, .22), inset 0 0 0 1px rgba(255, 255, 255, .16); }
        .launcher-tile:focus-visible .app-icon { outline: 3px solid var(--gold); outline-offset: 3px; }
        .app-icon-letter { grid-area: 1 / 1; }
        .app-icon-image { grid-area: 1 / 1; width: 100%; height: 100%; border-radius: 18px; object-fit: cover; }
        .launcher-name { font-size: 14px; font-weight: 750; line-height: 1.3; }
        .launcher-role { color: var(--muted); font-size: 12px; line-height: 1.3; }
        .icon-0 { background: linear-gradient(135deg, #d61f3c, #7a0c1d); }
        .icon-1 { background: linear-gradient(135deg, #2f6fed, #12327f); }
        .icon-2 { background: linear-gradient(135deg, #0ea472, #0a4d3b); }
        .icon-3 { background: linear-gradient(135deg, #d97706, #78350f); }
        .icon-4 { background: linear-gradient(135deg, #8b5cf6, #4c1d95); }
        .icon-5 { background: linear-gradient(135deg, #0891b2, #155e75); }
        .icon-6 { background: linear-gradient(135deg, #db2777, #831843); }
        .icon-7 { background: linear-gradient(135deg, #64748b, #1e293b); }
        .panel { padding: 28px; }
        .panel + .panel { margin-top: 24px; }
        .panel h2 { margin: 0 0 20px; font-size: 21px; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 20px; }
        .field-full { grid-column: 1 / -1; }
        .hint { margin: 7px 0 0; color: var(--muted); font-size: 13px; line-height: 1.45; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px 16px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: middle; }
        th { color: var(--muted); font-size: 12px; letter-spacing: .06em; text-transform: uppercase; }
        tbody tr:last-child td { border-bottom: 0; }
        .table-link { font-weight: 750; }
        .assignment { display: grid; grid-template-columns: minmax(180px, 1fr) minmax(160px, 220px); align-items: center; gap: 20px; padding: 18px 0; border-bottom: 1px solid var(--line); }
        .assignment:last-child { border-bottom: 0; }
        .assignment-title { font-weight: 750; }
        .assignment-path { margin-top: 4px; color: var(--muted); font-size: 13px; }
        .empty-action { margin-top: 24px !important; }
        .credential-list { margin: 0 0 22px; }
        .credential-list div { display: grid; grid-template-columns: 120px 1fr; gap: 16px; padding: 10px 0; border-bottom: 1px solid var(--line); }
        .credential-list dt { color: var(--muted); font-weight: 700; }
        .credential-list dd { margin: 0; overflow-wrap: anywhere; }
        .secret-value { margin-top: 10px; padding: 10px; overflow-wrap: anywhere; border: 1px solid #bbf7d0; border-radius: 6px; background: #fff; font-family: ui-monospace, SFMono-Regular, Consolas, monospace; }
        @media (max-width: 800px) {
            .topbar-inner { align-items: flex-start; flex-wrap: wrap; padding: 12px 0; }
            .topbar-actions { width: 100%; justify-content: space-between; }
        }
        @media (max-width: 640px) {
            .topbar-inner { min-height: 64px; }
            .account-name { display: none; }
            .main { padding-top: 32px; }
            .guest-main { padding: 32px 0; }
            .login-card { padding: 26px 22px; }
            .page-heading { align-items: start; flex-direction: column; }
            .form-grid { grid-template-columns: 1fr; }
            .field-full { grid-column: auto; }
            .assignment { grid-template-columns: 1fr; gap: 12px; }
            .nav { gap: 12px; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { scroll-behavior: auto !important; transition: none !important; }
        }
    </style>
</head>
<body>
<div class="shell">
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="{{ url('/') }}" aria-label="App Hub home">
                <span class="brand-mark" aria-hidden="true">AH</span>
                <span>App Hub</span>
            </a>
            @auth
                <div class="topbar-actions">
                    <nav class="nav" aria-label="Primary navigation">
                        <a href="{{ route('dashboard') }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif>Applications</a>
                        @if (auth()->user()->is_admin)
                            <a href="{{ route('admin.users.index') }}" @if (request()->routeIs('admin.users.*')) aria-current="page" @endif>Users</a>
                            <a href="{{ route('admin.applications.index') }}" @if (request()->routeIs('admin.applications.*')) aria-current="page" @endif>Manage apps</a>
                        @endif
                    </nav>
                    <div class="account">
                        <span class="avatar" aria-hidden="true">{{ auth()->user()->initials() }}</span>
                        <div class="account-meta">
                            <span class="account-name">{{ auth()->user()->name }}</span>
                            @if (auth()->user()->is_admin)
                                <span class="badge">Administrator</span>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="button button-link" type="submit">Sign out</button>
                        </form>
                    </div>
                </div>
            @endauth
        </div>
    </header>
    <main class="@auth main @else guest-main @endauth">
        @yield('content')
    </main>
</div>
</body>
</html>
