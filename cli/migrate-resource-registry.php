<?php
/**
 * Migrate: Shared Resource Registry
 *
 * Creates platform tables for cross-package resource sharing:
 *   - hub_resource_contracts  (canonical resource definitions)
 *   - hub_resource_providers  (which package owns which resource)
 *   - hub_resource_consumers  (which packages depend on which resource)
 *
 * Run: php cli/migrate-resource-registry.php
 */

require_once __DIR__ . '/../src/bootstrap.php';

use Hub\Database;

echo "=== Shared Resource Registry Migration ===\n\n";

$db = Database::getInstance();

try {
    $sqlFile = __DIR__ . '/../database/resource-registry-schema.sql';
    if (!file_exists($sqlFile)) {
        echo "ERROR: Schema file not found: {$sqlFile}\n";
        exit(1);
    }

    $sql = file_get_contents($sqlFile);

    // Split on semicolons (skip empty statements)
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function ($s) {
            // Remove comments-only lines
            $cleaned = preg_replace('/--.*$/m', '', $s);
            $cleaned = preg_replace('/\/\*.*?\*\//s', '', $cleaned);
            return !empty(trim($cleaned));
        }
    );

    $executed = 0;
    $skipped = 0;

    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if (empty($stmt)) continue;

        try {
            $db->getConnection()->exec($stmt);
            $executed++;

            // Identify what was executed
            if (preg_match('/CREATE TABLE.*?(\w+)/i', $stmt, $m)) {
                echo "  ✅ Created table: {$m[1]}\n";
            } elseif (preg_match('/INSERT/i', $stmt)) {
                echo "  ✅ Seeded contract data\n";
            } else {
                echo "  ✅ Executed statement\n";
            }
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'already exists')) {
                $skipped++;
                if (preg_match('/CREATE TABLE.*?(\w+)/i', $stmt, $m)) {
                    echo "  ⏭️  Table already exists: {$m[1]}\n";
                }
            } else {
                echo "  ❌ Error: " . $e->getMessage() . "\n";
                echo "     Statement: " . substr($stmt, 0, 100) . "...\n";
            }
        }
    }

    echo "\n✅ Migration complete: {$executed} executed, {$skipped} skipped\n";

    // Verify tables exist
    echo "\n--- Verification ---\n";
    $tables = ['hub_resource_contracts', 'hub_resource_providers', 'hub_resource_consumers'];
    foreach ($tables as $table) {
        $result = $db->getConnection()->query("SELECT COUNT(*) as cnt FROM {$table}")->fetch();
        echo "  {$table}: {$result['cnt']} rows\n";
    }

} catch (\Exception $e) {
    echo "FATAL: " . $e->getMessage() . "\n";
    exit(1);
}
