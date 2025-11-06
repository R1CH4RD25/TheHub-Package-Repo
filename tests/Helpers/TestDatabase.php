<?php

namespace Tests\Helpers;

use Hub\Database;

class TestDatabase
{
    private static $instance = null;
    private static $inTransaction = false;
    private static $tablesCreated = false;

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = Database::getInstance();
            self::ensureTablesExist();
        }
        return self::$instance;
    }

    /**
     * Ensure all required tables exist for testing
     */
    private static function ensureTablesExist(): void
    {
        if (self::$tablesCreated) {
            return;
        }

        $db = self::$instance;

        // Create users table
        $db->execute("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                google_id VARCHAR(255) UNIQUE NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                username VARCHAR(100) UNIQUE NULL,
                password VARCHAR(255) NULL,
                name VARCHAR(255) NOT NULL,
                role ENUM('staff', 'manager', 'admin', 'super_admin') DEFAULT 'staff',
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                last_login TIMESTAMP NULL,
                invited_by INT NULL,
                invited_at DATETIME NULL,
                approved_by INT NULL,
                approved_at DATETIME NULL,
                INDEX idx_email (email),
                INDEX idx_google_id (google_id),
                INDEX idx_username (username),
                INDEX idx_role (role),
                FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Create audit_log table
        $db->execute("
            CREATE TABLE IF NOT EXISTS audit_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                action VARCHAR(50) NOT NULL,
                table_name VARCHAR(50) NOT NULL,
                record_id INT NOT NULL,
                old_values TEXT,
                new_values TEXT,
                ip_address VARCHAR(45),
                user_agent VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_table_record (table_name, record_id),
                INDEX idx_created_at (created_at),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Create themes table
        $db->execute("
            CREATE TABLE IF NOT EXISTS themes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                settings JSON NOT NULL,
                is_active BOOLEAN DEFAULT FALSE,
                is_system BOOLEAN DEFAULT FALSE,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_is_active (is_active),
                INDEX idx_is_system (is_system),
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        self::$tablesCreated = true;
    }

    public static function beginTransaction(): void
    {
        if (!self::$inTransaction) {
            self::getInstance()->getConnection()->beginTransaction();
            self::$inTransaction = true;
        }
    }

    public static function rollBack(): void
    {
        if (self::$inTransaction) {
            self::getInstance()->getConnection()->rollBack();
            self::$inTransaction = false;
        }
    }

    public static function commit(): void
    {
        if (self::$inTransaction) {
            self::getInstance()->getConnection()->commit();
            self::$inTransaction = false;
        }
    }

    public static function cleanup(): void
    {
        // Clean up test data without dropping tables
        $db = self::getInstance();
        $conn = $db->getConnection();

        // Disable foreign key checks temporarily
        $conn->exec('SET FOREIGN_KEY_CHECKS = 0');

        // Truncate tables in safe order (children first) - skip if not exists
        try {
            $conn->exec('TRUNCATE TABLE audit_log');
        } catch (\PDOException $e) {
            // Table doesn't exist, skip
        }

        try {
            $conn->exec('TRUNCATE TABLE user_sessions');
        } catch (\PDOException $e) {
            // Table doesn't exist, skip
        }

        // Re-enable foreign key checks
        $conn->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Get raw PDO connection for direct queries
     */
    public static function getConnection(): \PDO
    {
        return self::getInstance()->getConnection();
    }

    /**
     * Create a test user with specified attributes
     *
     * @param array $attributes User attributes (email, role, name, etc.)
     * @return int User ID
     */
    public static function createTestUser(array $attributes = []): int
    {
        $db = self::getInstance();

        // Set defaults
        $email = $attributes['email'] ?? 'test_' . uniqid() . '@example.com';
        $name = $attributes['name'] ?? 'Test User';
        $role = $attributes['role'] ?? 'staff'; // Default to 'staff' (valid ENUM value)

        $userId = $db->insert('users', [
            'email' => $email,
            'name' => $name,
            'role' => $role,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return $userId;
    }
}
