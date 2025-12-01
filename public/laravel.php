<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Laravel Entry Point (Proof of Concept)
|--------------------------------------------------------------------------
| This file serves Laravel routes. The existing index.php remains for
| legacy authentication redirects. Laravel routes are accessed via
| .htaccess rewrite rules.
|--------------------------------------------------------------------------
*/

if (file_exists($maintenance = __DIR__ . '/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Bootstrap PHP session BEFORE Laravel starts
// This ensures Laravel uses the existing PHP session instead of creating a new one
require __DIR__ . '/../src/bootstrap.php';

require __DIR__ . '/../vendor/autoload.php';

/** @var Application $app */
// bootstrap.php already loaded the app into $GLOBALS['laravelApp']
$app = $GLOBALS['laravelApp'];

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$response->send();

$kernel->terminate($request, $response);
