#!/usr/bin/env php
<?php

/**
 * Package Forms Schema Migration
 * Run: php cli/migrate-package-forms.php
 */

require_once __DIR__ . '/../src/bootstrap.php';

use Hub\Database;

$db = Database::getInstance()->getConnection();

echo "🔧 Package Forms Schema Migration\n";
echo "==================================\n\n";

try {
    // Read and execute schema
    $schema = file_get_contents(__DIR__ . '/../database/package-forms-schema.sql');
    
    // Remove comments
    $schema = preg_replace('/^--.*$/m', '', $schema);
    
    // Split into individual statements
    $statements = explode(';', $schema);
    $statements = array_filter(array_map('trim', $statements));

    echo "Found " . count($statements) . " SQL statements\n\n";

    foreach ($statements as $i => $statement) {
        if (empty($statement)) continue;
        
        // Extract table name for display
        if (preg_match('/CREATE TABLE.*?`?(\w+)`?\s*\(/i', $statement, $matches)) {
            $tableName = $matches[1];
            echo "[$i] Creating table: $tableName... ";
            
            try {
                $db->exec($statement . ';');
                echo "✅\n";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'already exists') !== false) {
                    echo "⏭️  (already exists)\n";
                } else {
                    throw $e;
                }
            }
        }
    }

    echo "\n✅ Package Forms schema migration complete!\n\n";

    // Show table summary
    echo "📊 Tables Created:\n";
    $tables = [
        'package_forms',
        'package_form_fields',
        'package_form_submissions',
        'package_form_alerts',
        'package_form_alert_conditions',
        'package_form_alert_log',
        'package_form_access'
    ];

    foreach ($tables as $table) {
        $stmt = $db->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "  - $table: $count rows\n";
    }

    echo "\n";

} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n\n";
    exit(1);
}
