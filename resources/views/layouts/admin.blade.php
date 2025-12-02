<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin Dashboard') - The Hub</title>

    <!-- Enterprise Admin Bundle (Microsoft 365 Style) -->
    <link rel="stylesheet" href="/assets/css/admin-bundle.css">

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
        // Get user from request (injected by middleware)
        $user = request()->attributes->get('user');
        $userRole = $user['role'] ?? 'user';
        
        // Build admin nav items
        $navItems = [
            ['id' => 'users', 'label' => 'Users', 'url' => '/admin/users', 'icon' => 'fas fa-users'],
            ['id' => 'packages', 'label' => 'Packages', 'url' => '/admin/packages', 'icon' => 'fas fa-box'],
            ['id' => 'settings', 'label' => 'Settings', 'url' => '/admin/settings', 'icon' => 'fas fa-cog'],
            ['id' => 'logs', 'label' => 'Activity Logs', 'url' => '/admin/logs', 'icon' => 'fas fa-list-alt'],
            ['id' => 'export', 'label' => 'Export Data', 'url' => '/admin/export', 'icon' => 'fas fa-download'],
        ];
        
        // Render Enterprise Sidebar
        \Hub\Components\EnterpriseSidebar::render($user, $userRole, [
            'context' => 'admin',
            'title' => 'Admin',
            'icon' => 'fas fa-shield-alt',
            'logo_url' => '/admin/',
            'nav_items' => $navItems,
            'active_item' => null
        ]);

        // Render Enterprise Header
        \Hub\Components\EnterpriseHeader::render($user, $userRole, [
            'context' => 'admin',
            'breadcrumbs' => [
                ['label' => 'Home', 'url' => '/hub.php'],
                ['label' => 'Admin']
            ],
            'show_notifications' => true
        ]);
        ?>

        <!-- Main Content Area -->
        <main class="admin-main">
            @yield('content')
        </main>

        <?php
        // Render Enterprise Footer
        \Hub\Components\EnterpriseFooter::render([
            'context' => 'admin'
        ]);
        ?>
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
                x: 'right',
                y: 'top',
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
</body>
</html>
