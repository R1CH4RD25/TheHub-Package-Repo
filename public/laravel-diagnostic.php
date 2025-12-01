<?php

/**
 * Laravel Diagnostic - Show detailed errors
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Laravel Bootstrap Diagnostic</h1>";
echo "<pre>";

try {
    echo "1. Loading autoloader...\n";
    require __DIR__ . '/../vendor/autoload.php';
    echo "   ✓ Autoloader loaded\n\n";

    echo "2. Loading bootstrap/app.php...\n";
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    echo "   ✓ Application instance created\n";
    echo "   Type: " . get_class($app) . "\n\n";

    echo "3. Checking Laravel version...\n";
    echo "   Version: " . $app->version() . "\n\n";

    echo "4. Checking base path...\n";
    echo "   Base: " . $app->basePath() . "\n\n";

    echo "5. Attempting to handle request...\n";
    $request = \Illuminate\Http\Request::capture();
    echo "   ✓ Request captured: " . $request->method() . " " . $request->path() . "\n\n";

    echo "6. Processing request...\n";
    $app->handleRequest($request);
} catch (\Throwable $e) {
    echo "\n❌ ERROR CAUGHT:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    echo "Stack Trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "</pre>";
