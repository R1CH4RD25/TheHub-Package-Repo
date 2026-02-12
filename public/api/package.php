<?php

/**
 * Package API Endpoint (Layer 3)
 *
 * Handles AJAX requests for package queries and mutations.
 * All requests go through enforcement pipelines.
 *
 * Endpoints:
 *   GET  /api/package.php?action=query&package={id}&query={name}&...params
 *   POST /api/package.php?action=mutation  (body: {package, mutation, ...input})
 *
 * @see PACKAGE_ARCHITECTURE_SPEC.md
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\Package\PageRouter;

// Require login
Auth::requireLogin();

$user = Auth::getCurrentUser();
$userRole = Auth::getEffectiveRole();
$userArray = [
    'id' => $user['id'] ?? null,
    'role' => $userRole,
    'email' => $user['email'] ?? '',
    'name' => $user['display_name'] ?? $user['name'] ?? '',
];

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$router = new PageRouter();

try {
    switch ($action) {
        case 'query':
            // GET /api/package.php?action=query&package=com.woodson.student-directory&query=students.search&q=...
            if ($method !== 'GET') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed. Use GET for queries.']);
                exit;
            }

            $packageId = $_GET['package'] ?? '';
            $queryName = $_GET['query'] ?? '';

            if (!$packageId || !$queryName) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required parameters: package, query']);
                exit;
            }

            // Pass all GET params except our control params
            $params = $_GET;
            unset($params['action'], $params['package'], $params['query']);

            $result = $router->handleQuery($packageId, $queryName, $params, $userArray);
            $status = $result['status'] ?? 200;
            unset($result['status']);

            http_response_code($status);
            echo json_encode($result);
            break;

        case 'mutation':
            // POST /api/package.php?action=mutation
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed. Use POST for mutations.']);
                exit;
            }

            // CSRF check
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (stripos($contentType, 'application/json') !== false) {
                $input = json_decode(file_get_contents('php://input'), true) ?? [];
            } else {
                $input = $_POST;
            }

            $csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (!verifyCsrfToken($csrfToken)) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid CSRF token']);
                exit;
            }

            $packageId = $input['package'] ?? $_GET['package'] ?? '';
            $mutationName = $input['mutation'] ?? $_GET['mutation'] ?? '';

            if (!$packageId || !$mutationName) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required parameters: package, mutation']);
                exit;
            }

            // Remove control params from input
            unset($input['package'], $input['mutation'], $input['csrf_token']);

            $result = $router->handleMutation($packageId, $mutationName, $input, $userArray);
            $status = $result['status'] ?? 200;
            unset($result['status']);

            http_response_code($status);
            echo json_encode($result);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'error' => 'Unknown action. Use: query, mutation',
                'usage' => [
                    'query' => 'GET /api/package.php?action=query&package={id}&query={name}',
                    'mutation' => 'POST /api/package.php?action=mutation (body: {package, mutation, ...})',
                ],
            ]);
            break;
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage(),
    ]);

    // Log full error to file
    error_log("Package API error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
}
