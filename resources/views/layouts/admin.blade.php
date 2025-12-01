<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'TheHub' }}</title>
    <link rel="stylesheet" href="/assets/css/admin-bundle.css">
</head>
<body>
    <div class="admin-wrapper">
        <header class="admin-header">
            <h1>{{ $title ?? 'TheHub' }}</h1>
            <div class="user-info">
                Welcome, {{ $user->display_name ?? 'User' }}
            </div>
        </header>
        
        <main class="admin-main">
            @yield('content')
        </main>
        
        <footer class="admin-footer">
            <p>&copy; {{ date('Y') }} Woodson ISD. All rights reserved.</p>
        </footer>
    </div>
    
    @stack('scripts')
</body>
</html>
