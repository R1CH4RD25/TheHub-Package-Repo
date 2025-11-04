<?php

namespace Tests\Helpers;

use Hub\Database;

class TestDatabase
{
    private static $instance = null;
    private static $inTransaction = false;

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = Database::getInstance();
        }
        return self::$instance;
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
            'picture' => '/assets/images/default-avatar.svg',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return $userId;
    }
}
