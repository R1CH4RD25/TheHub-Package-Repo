#!/usr/bin/env php
<?php
/**
 * Package Migration Runner - Run database migrations for package installations and upgrades
 * 
 * Usage:
 *   php cli/pkg-migrate.php packages/local/my-package/
 *   php cli/pkg-migrate.php packages/local/my-package/ --from=1.0.0 --to=1.1.0
 *   php cli/pkg-migrate.php packages/local/my-package/ --rollback
 *   php cli/pkg-migrate.php packages/local/my-package/ --dry-run
 * 
 * Options:
 *   --from         Starting version (for upgrades)
 *   --to           Target version (for upgrades)
 *   --rollback     Roll back last migration
 *   --dry-run      Show SQL without executing
 *   --force        Skip confirmations
 * 
 * @author The Hub Team
 * @version 1.0.0
 */

// CLI only
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/bootstrap.php';

use Hub\Database;
use Hub\AuditLogger;

// Color output
class Colors {
    public static $enabled = true;
    
    const RESET = "\033[0m";
    const RED = "\033[31m";
    const GREEN = "\033[32m";
    const YELLOW = "\033[33m";
    const BLUE = "\033[34m";
    const CYAN = "\033[36m";
    const BOLD = "\033[1m";
    const DIM = "\033[2m";
    
    public static function red($text) { return self::$enabled ? self::RED . $text . self::RESET : $text; }
    public static function green($text) { return self::$enabled ? self::GREEN . $text . self::RESET : $text; }
    public static function yellow($text) { return self::$enabled ? self::YELLOW . $text . self::RESET : $text; }
    public static function blue($text) { return self::$enabled ? self::BLUE . $text . self::RESET : $text; }
    public static function cyan($text) { return self::$enabled ? self::CYAN . $text . self::RESET : $text; }
    public static function bold($text) { return self::$enabled ? self::BOLD . $text . self::RESET : $text; }
    public static function dim($text) { return self::$enabled ? self::DIM . $text . self::RESET : $text; }
}

class MigrationRunner {
    private $db;
    private $auditLogger;
    private $packageDir;
    private $migrationsDir;
    private $manifest;
    private $packageId;
    private $dryRun = false;
    private $force = false;
    
    public function __construct($packageDir, $options = []) {
        $this->packageDir = rtrim($packageDir, '/');
        $this->migrationsDir = $this->packageDir . '/migrations';
        $this->dryRun = isset($options['dry-run']);
        $this->force = isset($options['force']);
        
        $this->db = Database::getInstance();
        $this->auditLogger = new AuditLogger();
        
        // Load manifest
        $manifestPath = $this->packageDir . '/manifest.json';
        if (!file_exists($manifestPath)) {
            $this->error("manifest.json not found in {$this->packageDir}");
        }
        
        $json = file_get_contents($manifestPath);
        $this->manifest = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid manifest.json: ' . json_last_error_msg());
        }
        
        $this->packageId = $this->manifest['package']['id'] ?? null;
        if (!$this->packageId) {
            $this->error('Package ID not found in manifest');
        }
        
        // Ensure migrations table exists
        $this->ensureMigrationsTable();
    }
    
    public function run($fromVersion = null, $toVersion = null) {
        echo Colors::bold(Colors::blue("Package Migration Runner v1.0\n"));
        echo str_repeat('=', 80) . "\n\n";
        
        $currentVersion = $fromVersion ?? '0.0.0';
        $targetVersion = $toVersion ?? ($this->manifest['package']['version'] ?? '1.0.0');
        
        echo "Package: " . Colors::bold($this->packageId) . "\n";
        echo "From: " . Colors::cyan($currentVersion) . " → To: " . Colors::cyan($targetVersion) . "\n";
        echo "Mode: " . ($this->dryRun ? Colors::yellow("DRY RUN") : Colors::green("LIVE")) . "\n\n";
        
        // Find migration files
        $migrations = $this->findMigrations();
        
        if (empty($migrations)) {
            echo Colors::yellow("⚠ No migration files found in {$this->migrationsDir}\n");
            echo Colors::dim("Checking database schema from manifest...\n\n");
            
            // Run schema from manifest if exists
            if (!empty($this->manifest['db']['entities'])) {
                return $this->runSchemaFromManifest();
            }
            
            echo Colors::green("✓ No migrations needed\n");
            return true;
        }
        
        echo "Found " . count($migrations) . " migration file(s):\n";
        foreach ($migrations as $migration) {
            echo "  " . Colors::dim($migration['file']) . "\n";
        }
        echo "\n";
        
        // Filter migrations to run based on versions
        $migrationsToRun = $this->filterMigrationsByVersion($migrations, $currentVersion, $targetVersion);
        
        if (empty($migrationsToRun)) {
            echo Colors::green("✓ Database is up to date\n");
            return true;
        }
        
        echo "Migrations to run: " . Colors::bold(count($migrationsToRun)) . "\n\n";
        
        // Confirm before running
        if (!$this->dryRun && !$this->force) {
            echo "Continue? [y/N]: ";
            $handle = fopen("php://stdin", "r");
            $line = fgets($handle);
            fclose($handle);
            
            if (trim(strtolower($line)) !== 'y') {
                echo Colors::yellow("Migration cancelled\n");
                return false;
            }
            echo "\n";
        }
        
        // Run migrations
        $success = true;
        foreach ($migrationsToRun as $migration) {
            if (!$this->runMigration($migration)) {
                $success = false;
                break;
            }
        }
        
        if ($success) {
            echo "\n" . Colors::green(Colors::bold("✓ All migrations completed successfully!\n"));
        } else {
            echo "\n" . Colors::red(Colors::bold("✗ Migration failed\n"));
        }
        
        return $success;
    }
    
    public function rollback() {
        echo Colors::bold(Colors::blue("Package Migration Rollback\n"));
        echo str_repeat('=', 80) . "\n\n";
        
        // Get last migration
        $lastMigration = $this->getLastMigration();
        
        if (!$lastMigration) {
            echo Colors::yellow("⚠ No migrations to roll back\n");
            return false;
        }
        
        echo "Rolling back: " . Colors::bold($lastMigration['migration']) . "\n";
        echo "Ran at: " . Colors::dim($lastMigration['executed_at']) . "\n\n";
        
        // Confirm
        if (!$this->force) {
            echo Colors::red("WARNING: This will roll back the database!\n");
            echo "Continue? [y/N]: ";
            $handle = fopen("php://stdin", "r");
            $line = fgets($handle);
            fclose($handle);
            
            if (trim(strtolower($line)) !== 'y') {
                echo Colors::yellow("Rollback cancelled\n");
                return false;
            }
            echo "\n";
        }
        
        // Find rollback SQL
        $migrationFile = $this->migrationsDir . '/' . $lastMigration['migration'];
        
        if (!file_exists($migrationFile)) {
            echo Colors::red("✗ Migration file not found: {$migrationFile}\n");
            return false;
        }
        
        $rollbackSql = $this->extractRollbackSql($migrationFile);
        
        if (!$rollbackSql) {
            echo Colors::red("✗ No rollback SQL found in migration file\n");
            return false;
        }
        
        // Execute rollback
        try {
            $this->db->getPdo()->beginTransaction();
            
            $this->db->getPdo()->exec($rollbackSql);
            
            // Remove migration record
            $this->db->execute(
                "DELETE FROM package_migrations WHERE package_id = ? AND migration = ?",
                [$this->packageId, $lastMigration['migration']]
            );
            
            $this->db->getPdo()->commit();
            
            echo Colors::green("✓ Rollback successful\n");
            
            // Log to audit
            $this->auditLogger->log(
                'package_migration_rollback',
                'package_migrations',
                $lastMigration['id'],
                ['migration' => $lastMigration['migration']],
                null,
                $_SESSION['user_id'] ?? null
            );
            
            return true;
            
        } catch (Exception $e) {
            $this->db->getPdo()->rollBack();
            echo Colors::red("✗ Rollback failed: " . $e->getMessage() . "\n");
            return false;
        }
    }
    
    private function runSchemaFromManifest(): bool {
        echo Colors::bold("Running schema from manifest...\n\n");
        
        try {
            $this->db->getPdo()->beginTransaction();
            
            foreach ($this->manifest['db']['entities'] as $entity) {
                $tableName = $entity['name'];
                $fields = $entity['fields'];
                
                echo "Creating table: " . Colors::cyan($tableName) . "\n";
                
                // Build CREATE TABLE statement
                $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (\n";
                $sql .= "  " . implode(",\n  ", $fields);
                $sql .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
                
                if ($this->dryRun) {
                    echo Colors::dim($sql) . "\n\n";
                } else {
                    $this->db->getPdo()->exec($sql);
                    echo Colors::green("  ✓ Created\n");
                }
                
                // Add indexes if defined
                if (!empty($entity['indexes'])) {
                    foreach ($entity['indexes'] as $index) {
                        if ($this->dryRun) {
                            echo Colors::dim("  ALTER TABLE `{$tableName}` ADD {$index};") . "\n";
                        } else {
                            $this->db->getPdo()->exec("ALTER TABLE `{$tableName}` ADD {$index};");
                        }
                    }
                }
                
                // Add foreign keys if defined
                if (!empty($entity['foreignKeys'])) {
                    foreach ($entity['foreignKeys'] as $fk) {
                        if ($this->dryRun) {
                            echo Colors::dim("  ALTER TABLE `{$tableName}` ADD {$fk};") . "\n";
                        } else {
                            try {
                                $this->db->getPdo()->exec("ALTER TABLE `{$tableName}` ADD {$fk};");
                            } catch (Exception $e) {
                                // Foreign key might already exist, that's okay
                                echo Colors::yellow("  ⚠ Foreign key warning: " . $e->getMessage() . "\n");
                            }
                        }
                    }
                }
            }
            
            if (!$this->dryRun) {
                $this->db->getPdo()->commit();
                
                // Record migration
                $this->recordMigration('manifest_schema', 'Schema from manifest.json');
            }
            
            echo "\n" . Colors::green("✓ Schema created successfully\n");
            return true;
            
        } catch (Exception $e) {
            if (!$this->dryRun) {
                $this->db->getPdo()->rollBack();
            }
            echo Colors::red("✗ Schema creation failed: " . $e->getMessage() . "\n");
            return false;
        }
    }
    
    private function runMigration($migration): bool {
        $file = $migration['file'];
        $path = $migration['path'];
        
        echo "Running: " . Colors::cyan($file) . "\n";
        
        // Read migration file
        $sql = file_get_contents($path);
        
        // Extract UP migration (everything before -- ROLLBACK or end of file)
        $upSql = $this->extractUpSql($sql);
        
        if ($this->dryRun) {
            echo Colors::dim($upSql) . "\n\n";
            return true;
        }
        
        try {
            $this->db->getPdo()->beginTransaction();
            
            // Execute migration
            $this->db->getPdo()->exec($upSql);
            
            // Record migration
            $this->recordMigration($file, "Migration from {$file}");
            
            $this->db->getPdo()->commit();
            
            echo Colors::green("  ✓ Success\n");
            
            // Log to audit
            $this->auditLogger->log(
                'package_migration',
                'package_migrations',
                null,
                null,
                ['package_id' => $this->packageId, 'migration' => $file],
                $_SESSION['user_id'] ?? null
            );
            
            return true;
            
        } catch (Exception $e) {
            $this->db->getPdo()->rollBack();
            echo Colors::red("  ✗ Failed: " . $e->getMessage() . "\n");
            return false;
        }
    }
    
    private function findMigrations(): array {
        if (!is_dir($this->migrationsDir)) {
            return [];
        }
        
        $files = scandir($this->migrationsDir);
        $migrations = [];
        
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                $migrations[] = [
                    'file' => $file,
                    'path' => $this->migrationsDir . '/' . $file,
                    'order' => $this->extractOrder($file)
                ];
            }
        }
        
        // Sort by order
        usort($migrations, function($a, $b) {
            return $a['order'] - $b['order'];
        });
        
        return $migrations;
    }
    
    private function filterMigrationsByVersion($migrations, $fromVersion, $toVersion): array {
        // Get already run migrations
        $runMigrations = $this->getRunMigrations();
        
        // Filter out already run migrations
        return array_filter($migrations, function($migration) use ($runMigrations) {
            return !in_array($migration['file'], $runMigrations);
        });
    }
    
    private function getRunMigrations(): array {
        $result = $this->db->fetchAll(
            "SELECT migration FROM package_migrations WHERE package_id = ? ORDER BY executed_at",
            [$this->packageId]
        );
        
        return array_column($result, 'migration');
    }
    
    private function getLastMigration() {
        return $this->db->fetchOne(
            "SELECT * FROM package_migrations WHERE package_id = ? ORDER BY executed_at DESC LIMIT 1",
            [$this->packageId]
        );
    }
    
    private function recordMigration($migrationName, $description) {
        $this->db->execute(
            "INSERT INTO package_migrations (package_id, migration, description, executed_at) VALUES (?, ?, ?, NOW())",
            [$this->packageId, $migrationName, $description]
        );
    }
    
    private function extractOrder($filename): int {
        // Extract leading number from filename (e.g., "001_create_tables.sql" -> 1)
        if (preg_match('/^(\d+)/', $filename, $matches)) {
            return intval($matches[1]);
        }
        return 0;
    }
    
    private function extractUpSql($sql): string {
        // Split by -- ROLLBACK comment
        $parts = preg_split('/-- ROLLBACK/i', $sql);
        return trim($parts[0]);
    }
    
    private function extractRollbackSql($file): ?string {
        $content = file_get_contents($file);
        
        // Find -- ROLLBACK section
        if (preg_match('/-- ROLLBACK\s*\n(.*)/is', $content, $matches)) {
            return trim($matches[1]);
        }
        
        return null;
    }
    
    private function ensureMigrationsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS package_migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            package_id VARCHAR(100) NOT NULL,
            migration VARCHAR(255) NOT NULL,
            description TEXT,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_migration (package_id, migration),
            INDEX idx_package (package_id),
            INDEX idx_executed (executed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        try {
            $this->db->getPdo()->exec($sql);
        } catch (Exception $e) {
            // Table might already exist
        }
    }
    
    private function error($message) {
        echo Colors::red("Error: {$message}\n");
        exit(1);
    }
}

// Parse command line arguments
$packageDir = $argv[1] ?? null;
$options = [];

for ($i = 2; $i < $argc; $i++) {
    if (str_starts_with($argv[$i], '--')) {
        $arg = substr($argv[$i], 2);
        if (str_contains($arg, '=')) {
            list($key, $value) = explode('=', $arg, 2);
            $options[$key] = $value;
        } else {
            $options[$arg] = true;
        }
    }
}

// Show usage if no package directory
if (!$packageDir) {
    echo Colors::bold("Package Migration Runner v1.0\n");
    echo str_repeat('=', 80) . "\n\n";
    echo "Usage:\n";
    echo "  php cli/pkg-migrate.php <package-dir> [options]\n\n";
    echo "Options:\n";
    echo "  --from=VERSION     Starting version (for upgrades)\n";
    echo "  --to=VERSION       Target version (for upgrades)\n";
    echo "  --rollback         Roll back last migration\n";
    echo "  --dry-run          Show SQL without executing\n";
    echo "  --force            Skip confirmations\n\n";
    echo "Examples:\n";
    echo "  php cli/pkg-migrate.php packages/local/my-package/\n";
    echo "  php cli/pkg-migrate.php packages/local/my-package/ --from=1.0.0 --to=1.1.0\n";
    echo "  php cli/pkg-migrate.php packages/local/my-package/ --rollback\n";
    echo "  php cli/pkg-migrate.php packages/local/my-package/ --dry-run\n\n";
    exit(0);
}

// Run migration
try {
    $runner = new MigrationRunner($packageDir, $options);
    
    if (isset($options['rollback'])) {
        $success = $runner->rollback();
    } else {
        $fromVersion = $options['from'] ?? null;
        $toVersion = $options['to'] ?? null;
        $success = $runner->run($fromVersion, $toVersion);
    }
    
    exit($success ? 0 : 1);
    
} catch (Exception $e) {
    echo Colors::red("Fatal error: " . $e->getMessage() . "\n");
    echo Colors::dim($e->getTraceAsString() . "\n");
    exit(1);
}
