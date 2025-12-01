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

require __DIR__ . '/../vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__ . '/../bootstrap/app.php';

$app->handleRequest(Request::capture());
