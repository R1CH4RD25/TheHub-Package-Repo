#!/usr/bin/env php
<?php
/**
 * Database Migration Script
 * Run this to set up or update the database schema
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$host = $_ENV['DB_HOST'];
$dbname = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$pass = $_ENV['DB_PASS'];

try {
    echo "Connecting to database...\n";
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if it doesn't exist
    echo "Creating database if not exists...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");
    
    // Read and execute schema file
    echo "Running schema migration...\n";
    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
    
    // Remove comments
    $schema = preg_replace('/^--.*$/m', '', $schema);
    
    // Split by semicolons and execute each statement
    $statements = explode(';', $schema);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // Ignore "already exists" errors for views
                if (strpos($e->getMessage(), 'already exists') === false) {
                    echo "Warning: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    echo "✓ Database schema created successfully!\n";
    
    // Check if super admin exists, if not prompt to create
    $superAdminEmail = $_ENV['SUPER_ADMIN_EMAIL'];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$superAdminEmail]);
    
    if ($stmt->fetchColumn() == 0) {
        echo "\nNote: Super admin ($superAdminEmail) will be created on first Google login.\n";
        echo "Make sure to log in with this account first.\n";
    } else {
        echo "\n✓ Super admin account exists.\n";
    }
    
    echo "\nMigration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
