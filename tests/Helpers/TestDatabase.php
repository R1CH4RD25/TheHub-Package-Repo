<?php

namespace Tests\Helpers;

use Hub\Database;

/**
 * Test helper for database transaction management
 * Provides transaction isolation for integration tests
 */
class TestDatabase
{
    /**
     * Get database instance
     */
    public static function getInstance(): Database
    {
        return Database::getInstance();
    }
    
    /**
     * Begin a transaction for test isolation
     */
    public static function beginTransaction(): void
    {
        $db = self::getInstance();
        $db->beginTransaction();
    }
    
    /**
     * Roll back the transaction after test completion
     */
    public static function rollBack(): void
    {
        $db = self::getInstance();
        if ($db->inTransaction()) {
            $db->rollBack();
        }
    }
    
    /**
     * Commit the transaction (rarely needed in tests)
     */
    public static function commit(): void
    {
        $db = self::getInstance();
        if ($db->inTransaction()) {
            $db->commit();
        }
    }
}
