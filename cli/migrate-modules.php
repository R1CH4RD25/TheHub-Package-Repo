<?php

require_once __DIR__ . '/../src/bootstrap.php';

use Hub\Database;

echo "Running module system migration...\n\n";

$db = Database::getInstance();

try {
    // Read and execute the modules schema
    $sql = file_get_contents(__DIR__ . '/../database/modules-schema.sql');
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (empty($statement)) continue;
        
        echo "Executing: " . substr($statement, 0, 50) . "...\n";
        $db->execute($statement);
    }
    
    echo "\n✓ Module system migration completed successfully!\n\n";
    
    // Grant all existing users access to the vehicles module
    echo "Granting existing users access to Vehicle Maintenance module...\n";
    
    $users = $db->fetchAll("SELECT id, role FROM users WHERE is_active = TRUE");
    $vehicleModule = $db->fetchOne("SELECT id FROM modules WHERE slug = 'vehicles'");
    
    if ($vehicleModule) {
        foreach ($users as $user) {
            $db->execute(
                "INSERT INTO user_module_access (user_id, module_id, role, granted_by) 
                 VALUES (?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE role = VALUES(role)",
                [$user['id'], $vehicleModule['id'], $user['role']]
            );
        }
        echo "✓ Granted " . count($users) . " users access to Vehicle Maintenance\n";
    }
    
    echo "\n✓ Migration complete!\n";
    
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
