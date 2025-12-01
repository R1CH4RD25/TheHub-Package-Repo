<?php

/**
 * Laravel Health Check - Minimal Test
 * Tests if Laravel routing works without views
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

$app = require_once __DIR__ . '/../bootstrap/app.php';

try {
    $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
    $request = Request::capture();
    $response = $kernel->handle($request);
    $response->send();
    $kernel->terminate($request, $response);
} catch (\Exception $e) {
    // Fallback: Return JSON error
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
}
