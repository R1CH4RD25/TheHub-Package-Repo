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
    
    error_log("=== GOOGLE_LOGIN.PHP (CALLBACK): User logged in: {$user['email']} (ID: {$user['id']}, Role: {$user['role']}) ===");
    error_log("=== GOOGLE_LOGIN.PHP (CALLBACK): Redirecting to hub ===");
    
    // Redirect to Hub (section selector)
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
