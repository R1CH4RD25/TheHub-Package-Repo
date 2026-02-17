<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') - The Hub</title>

    <!-- Enterprise Bundle (Admin/Management) -->
    @if(isset($context) && $context === 'management')
        <link rel="stylesheet" href="/assets/css/mgmt-bundle.css?v={{ filemtime(public_path('assets/css/mgmt-bundle.css')) }}">
    @else
        <!-- DEBUG: filemtime={{ filemtime(public_path('assets/css/admin-bundle.css')) }} stat={{ fileatime(public_path('assets/css/admin-bundle.css')) }} -->
        <link rel="stylesheet" href="/assets/css/admin-bundle.css?v={{ filemtime(public_path('assets/css/admin-bundle.css')) }}">
    @endif

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Notyf - Toast Notifications -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">

    <!-- SweetAlert2 - Beautiful Modals -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">

    @stack('styles')
</head>

<body class="admin-root">
    <div class="admin-shell">
        <?php
        // Get data from view
        $context = $context ?? 'admin';
        $user = $user ?? request()->attributes->get('user');
        $userRole = $user['role'] ?? 'user';
        $navItems = $navItems ?? [];
        $activeItem = $activeItem ?? null;
        $sidebarTitle = $sidebarTitle ?? 'Dashboard';
        $sidebarIcon = $sidebarIcon ?? 'fas fa-dashboard';
        $logoUrl = $logoUrl ?? '/';
        $breadcrumbs = $breadcrumbs ?? [];

        // Render Enterprise Sidebar
        \Hub\Components\EnterpriseSidebar::render($user, $userRole, [
            'context' => $context,
            'title' => $sidebarTitle,
            'icon' => $sidebarIcon,
            'logo_url' => $logoUrl,
            'nav_items' => $navItems,
            'active_item' => $activeItem
        ]);

        // Render Enterprise Header
        \Hub\Components\EnterpriseHeader::render($user, $userRole, [
            'context' => $context,
            'breadcrumbs' => $breadcrumbs,
            'show_notifications' => true
        ]);
        ?>

        <!-- Main Content Area -->
        <main class="admin-main">
            @yield('content')
        </main>

        @if($context === 'management')
        <?php
        // Render Enterprise Footer for Management
        \Hub\Components\EnterpriseFooter::render($user, [
            'context' => 'management',
            'show_version' => true,
            'show_user' => false,
            'show_custom_text' => true
        ]);
        ?>
        @endif
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- Notyf -->
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>

    <!-- Initialize Notyf -->
    <script>
        const notyf = new Notyf({
            duration: 3000,
            position: {
                x: 'center',
                y: 'bottom',
            },
            types: [
                {
                    type: 'success',
                    background: '#10b981',
                    icon: {
                        className: 'fas fa-check-circle',
                        tagName: 'i',
                        color: 'white'
                    }
                },
                {
                    type: 'error',
                    background: '#ef4444',
                    icon: {
                        className: 'fas fa-exclamation-triangle',
                        tagName: 'i',
                        color: 'white'
                    }
                }
            ]
        });
    </script>

    @stack('scripts')

    <!-- Admin Dashboard Responsive Debug -->
    <script src="https://hub.woodsonisd.net/assets/js/responsive-debug.js"></script>
</body>
</html>
