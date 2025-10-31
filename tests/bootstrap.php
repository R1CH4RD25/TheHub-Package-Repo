<?php
/**
 * PHPUnit Bootstrap
 * 
 * Set up testing environment, load dependencies, configure test database
 */

// Composer autoload
require_once __DIR__ . '/../vendor/autoload.php';

// Load .env for testing
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Override environment for testing
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_NAME'] = $_ENV['DB_NAME_TEST'] ?? 'hub_test';

// Start session for tests that need it
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set default timezone
date_default_timezone_set('America/Chicago');

echo "PHPUnit Bootstrap - Testing environment loaded\n";

// Manually load test helper
require_once __DIR__ . '/Helpers/TestDatabase.php';
