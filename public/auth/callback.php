<?php

require_once __DIR__ . '/../src/bootstrap.php';

use Hub\Auth;

// Get the authorization code from Google
$code = $_GET['code'] ?? null;

if (!$code) {
    header('Location: /login.php');
    exit;
}

try {
    $auth = new Auth();
    $user = $auth->handleCallback($code);
    
    error_log("=== AUTH CALLBACK: User logged in: {$user['email']} (ID: {$user['id']}, Role: {$user['role']}) ===");
    error_log("=== AUTH CALLBACK: Redirecting to hub ===");
    
    // Redirect to hub page (dynamic based on role)
    // Use Cache-Control to prevent browser from caching this redirect
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Location: /');
    exit;
    
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: /login.php');
    exit;
}
