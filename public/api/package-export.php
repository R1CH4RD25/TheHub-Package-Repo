<?php

/**
 * Package Export API Endpoint
 *
 * Handles data export requests from package tables/queries.
 *
 * Endpoints:
 *   POST /api/package-export.php
 *     Body (JSON): { packageId, queryName, format, params, columns, csrfToken }
 *     Response: File download (CSV/XLSX/JSON)
 *
 * Security:
 * - CSRF token required
 * - Authentication required
 * - Export goes through enforcement pipeline (scoping + masking)
 * - Separate rate limit for exports
 * - Full audit logging
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\Package\ExportHandler;
use Hub\Package\PolicyEngine;
use Hub\Package\QueryRouter;
use Hub\Package\ScopeEngine;
use Hub\Package\HandlerRegistry;
use Hub\Package\ProjectionEngine;

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Require authentication
$user = Auth::user();
if (!$user) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Parse body
$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true);

if (!$body || !is_array($body)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

$packageId = $body['packageId'] ?? '';
$queryName = $body['queryName'] ?? '';
$format = $body['format'] ?? 'csv';
$params = $body['params'] ?? [];
$columns = $body['columns'] ?? [];
$csrfToken = $body['csrfToken'] ?? $body['csrf_token'] ?? '';

// Validate required
if (!$packageId || !$queryName) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing required fields: packageId, queryName']);
    exit;
}

// CSRF validation
$sessionToken = $_SESSION['csrf_token'] ?? '';
if (!$csrfToken || !$sessionToken || !hash_equals($sessionToken, $csrfToken)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'CSRF token validation failed']);
    exit;
}

try {
    // Build enforcement pipeline
    $policy = new PolicyEngine();
    $scope = new ScopeEngine();
    $registry = HandlerRegistry::getInstance();
    $projection = new ProjectionEngine();

    $queryRouter = new QueryRouter($policy, $scope, $registry, $projection);
    $exportHandler = new ExportHandler($policy, $queryRouter);

    // Execute export
    $result = $exportHandler->export(
        $user,
        $packageId,
        $queryName,
        $params,
        $format,
        $columns
    );

    // Stream result as file download
    $exportHandler->stream($result);
} catch (\RuntimeException $e) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
} catch (\Exception $e) {
    error_log("[PackageExport] Error: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Export failed']);
}
