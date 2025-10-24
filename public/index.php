<?php

require_once __DIR__ . '/../src/bootstrap.php';

use Hub\Auth;

// Redirect if already logged in
if (Auth::getCurrentUser()) {
    error_log("=== INDEX.PHP: User already logged in, redirecting to hub ===");
    // Go to hub page (dynamic based on role)
    header('Location: /');
    exit;
}

// Redirect to login page
error_log("=== INDEX.PHP: No user session, redirecting to /login.php ===");
header('Location: /login.php');
exit;
