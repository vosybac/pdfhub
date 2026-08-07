<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'PDFHub') — Author Network Explorer</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script>
        window.PDFHub = {
            csrfToken: @json(csrf_token()),
        };
    </script>
    @stack('head')
</head>
<body>
    <div class="shell">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-logo">PDF</div>
                <div class="brand-text">
                    <strong>PDFHub</strong>
                    <span>Author Network</span>
                </div>
            </div>
            <nav class="nav">
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <span class="nav-icon">◎</span> Dashboard
                </a>
                <a href="{{ route('documents.upload') }}" class="nav-link {{ request()->routeIs('documents.upload') ? 'active' : '' }}">
                    <span class="nav-icon">↑</span> Upload PDFs
                </a>
                <a href="{{ route('documents.index') }}" class="nav-link {{ request()->routeIs('documents.*') && !request()->routeIs('documents.upload') ? 'active' : '' }}">
                    <span class="nav-icon">▤</span> Documents
                </a>
                <a href="{{ route('authors.index') }}" class="nav-link {{ request()->routeIs('authors.*') ? 'active' : '' }}">
                    <span class="nav-icon">✦</span> Authors
                </a>
            </nav>
            <div class="sidebar-footer">
                Background queue: <strong id="queue-health">—</strong>
            </div>
        </aside>
        <main class="main">
            <header class="topbar">
                <h1>@yield('page-title', 'PDFHub')</h1>
                <div class="topbar-right">
                    @yield('topbar-actions')
                </div>
            </header>
            <div class="content">
                @if (session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif
                @yield('content')
            </div>
        </main>
    </div>
    <script src="{{ asset('vendor/vis-network.min.js') }}"></script>
    @stack('scripts')
</body>
</html>
