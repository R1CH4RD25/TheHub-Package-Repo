<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;

Auth::requireLogin();

$user = Auth::getCurrentUser();

// Only super admin can switch roles
if ($user['actual_role'] !== 'super_admin') {
    jsonResponse(['error' => 'Unauthorized'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$role = $input['role'] ?? null;

$validRoles = ['staff', 'manager', 'admin', 'super_admin'];

if (!$role || !in_array($role, $validRoles)) {
    jsonResponse(['error' => 'Invalid role'], 400);
}

if ($role === 'super_admin') {
    Auth::clearViewAsRole();
} else {
    Auth::setViewAsRole($role);
}

jsonResponse(['success' => true, 'role' => $role]);
