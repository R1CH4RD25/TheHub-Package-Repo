<?php
/**
 * Roles API - Get all available roles
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Roles;

// Return roles as JSON
header('Content-Type: application/json');
echo Roles::getForJavaScript();
