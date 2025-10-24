#!/usr/bin/env php
<?php
/**
 * Dynamic Sections Migration
 * Creates all necessary tables for the section builder system
 */

require_once __DIR__ . '/../src/bootstrap.php';

use Hub\Database;

$db = Database::getInstance();

echo "🚀 Dynamic Sections Migration\n";
echo "============================\n\n";

try {
    // Read the schema file
    $schemaFile = __DIR__ . '/../database/dynamic-sections-schema.sql';
    
    if (!file_exists($schemaFile)) {
        throw new Exception("Schema file not found: $schemaFile");
    }
    
    echo "📖 Reading schema file...\n";
    $sql = file_get_contents($schemaFile);
    
    // Split into individual statements (simple split by semicolon)
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            // Filter out comments and empty statements
            $stmt = trim($stmt);
            return !empty($stmt) && 
                   !preg_match('/^--/', $stmt) && 
                   !preg_match('/^\/\*/', $stmt);
        }
    );
    
    echo "📊 Found " . count($statements) . " SQL statements to execute\n\n";
    
    $executed = 0;
    $skipped = 0;
    $errors = 0;
    
    foreach ($statements as $statement) {
        // Get table name for logging
        if (preg_match('/CREATE TABLE.*`?(\w+)`?/i', $statement, $matches)) {
            $tableName = $matches[1];
            echo "  📦 Creating table: $tableName... ";
            
            try {
                $db->execute($statement);
                echo "✅\n";
                $executed++;
            } catch (\PDOException $e) {
                if (strpos($e->getMessage(), 'already exists') !== false) {
                    echo "⚠️  (already exists)\n";
                    $skipped++;
                } else {
                    echo "❌ ERROR: " . $e->getMessage() . "\n";
                    $errors++;
                }
            }
        } elseif (preg_match('/CREATE.*VIEW.*`?(\w+)`?/i', $statement, $matches)) {
            $viewName = $matches[1];
            echo "  👁️  Creating view: $viewName... ";
            
            try {
                $db->execute($statement);
                echo "✅\n";
                $executed++;
            } catch (\PDOException $e) {
                echo "❌ ERROR: " . $e->getMessage() . "\n";
                $errors++;
            }
        } elseif (preg_match('/ALTER TABLE/i', $statement)) {
            echo "  🔧 Adding index... ";
            try {
                $db->execute($statement);
                echo "✅\n";
                $executed++;
            } catch (\PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate key') !== false) {
                    echo "⚠️  (already exists)\n";
                    $skipped++;
                } else {
                    echo "❌ ERROR: " . $e->getMessage() . "\n";
                    $errors++;
                }
            }
        }
    }
    
    echo "\n============================\n";
    echo "✅ Migration Complete!\n";
    echo "   Executed: $executed\n";
    echo "   Skipped:  $skipped\n";
    echo "   Errors:   $errors\n";
    
    if ($errors === 0) {
        echo "\n🎉 All tables created successfully!\n";
        
        // Verify tables exist
        echo "\n🔍 Verifying tables...\n";
        $tables = [
            'section_packages',
            'section_installations',
            'section_field_definitions',
            'section_records',
            'section_record_history',
            'section_administrators',
            'section_menu_items',
            'section_record_attachments',
            'section_workflows',
            'section_workflow_instances',
            'section_workflow_actions'
        ];
        
        foreach ($tables as $table) {
            $result = $db->fetchOne("SHOW TABLES LIKE '$table'");
            if ($result) {
                echo "   ✅ $table\n";
            } else {
                echo "   ❌ $table (MISSING!)\n";
            }
        }
        
        echo "\n✨ Database is ready for Dynamic Sections!\n";
    } else {
        echo "\n⚠️  Some errors occurred. Please review above.\n";
        exit(1);
    }
    
} catch (\Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
