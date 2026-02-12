<?php

/**
 * Package Mutation API Endpoint
 *
 * Routes form submissions and mutations through the MutationRouter enforcement pipeline.
 *
 * Endpoints:
 *   POST /api/package-mutation.php
 *     Body (JSON): { packageId, mutationName, input, csrfToken }
 *     Response: { success, data, message, meta }
 *
 * Security:
 * - CSRF token required
 * - Authentication required
 * - All mutations go through 10-step enforcement pipeline
 * - Rate limiting applied per-mutation
 *
 * @see MutationRouter.php for the enforcement pipeline
 * @see PACKAGE_ARCHITECTURE_SPEC.md §12
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\Database;
use Hub\Package\PolicyEngine;
use Hub\Package\ScopeEngine;
use Hub\Package\HandlerRegistry;
use Hub\Package\MutationRouter;
use Hub\Package\PackageLoader;
use Hub\Package\PageRouter;

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed', 'status' => 405]);
    exit;
}

header('Content-Type: application/json');

// Require authentication
$user = Auth::user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required', 'status' => 401]);
    exit;
}

// Parse JSON body
$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true);

if (!$body || !is_array($body)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body', 'status' => 400]);
    exit;
}

$packageId = $body['packageId'] ?? '';
$mutationName = $body['mutationName'] ?? '';
$input = $body['input'] ?? [];
$csrfToken = $body['csrfToken'] ?? $body['csrf_token'] ?? '';

// Validate required fields
if (!$packageId || !$mutationName) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Missing required fields: packageId, mutationName',
        'status' => 400,
    ]);
    exit;
}

// CSRF validation
$sessionToken = $_SESSION['csrf_token'] ?? '';
if (!$csrfToken || !$sessionToken || !hash_equals($sessionToken, $csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF token validation failed', 'status' => 403]);
    exit;
}

try {
    // Build the enforcement pipeline
    $policy = new PolicyEngine();
    $scope = new ScopeEngine();
    $registry = HandlerRegistry::getInstance();

    $mutationRouter = new MutationRouter($policy, $scope, $registry);
    $pageRouter = new PageRouter(null, null, $policy, null, $mutationRouter);

    // Route through PageRouter which finds the page config for the mutation
    $result = $pageRouter->handleMutation($packageId, $mutationName, $input, $user);

    $status = $result['status'] ?? 200;
    http_response_code($status);

    echo json_encode($result);
} catch (\RuntimeException $e) {
    // Security/validation errors
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'status' => 400,
    ]);
} catch (\Exception $e) {
    // Unexpected errors
    error_log("[PackageMutation] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An unexpected error occurred',
        'status' => 500,
    ]);
}
