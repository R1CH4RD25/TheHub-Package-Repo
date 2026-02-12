<?php

namespace Hub\Package\StudentDirectory;

use Hub\Database;

/**
 * Student Database Connection
 *
 * Provides access to the woodson_students database.
 * Uses the Hub's DB credentials with cross-database grants.
 *
 * The students data lives in a separate database (woodson_students)
 * from the Hub core (woodson_hub). This class wraps a PDO connection
 * to that database for all student directory operations.
 */
class StudentDatabase
{
    private static ?\PDO $connection = null;

    /**
     * Get PDO connection to woodson_students database
     */
    public static function getConnection(): \PDO
    {
        if (self::$connection === null) {
            $host = env('DB_HOST', '127.0.0.1');
            $port = env('DB_PORT', '3306');
            $database = env('STUDENT_DB_DATABASE', 'woodson_students');
            $username = env('DB_USERNAME', 'WISDAdmin');
            $password = env('DB_PASSWORD', '');

            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

            self::$connection = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
        }

        return self::$connection;
    }

    /**
     * Execute a query and return all rows
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a query and return one row
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Execute a query and return the scalar value of the first column
     */
    public static function fetchColumn(string $sql, array $params = []): mixed
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * Execute an INSERT/UPDATE/DELETE and return affected row count
     */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Insert a row and return the last insert ID
     */
    public static function insert(string $sql, array $params = []): int
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return (int) self::getConnection()->lastInsertId();
    }

    /**
     * Begin a transaction
     */
    public static function beginTransaction(): void
    {
        self::getConnection()->beginTransaction();
    }

    /**
     * Commit a transaction
     */
    public static function commit(): void
    {
        self::getConnection()->commit();
    }

    /**
     * Roll back a transaction
     */
    public static function rollBack(): void
    {
        self::getConnection()->rollBack();
    }

    /**
     * Reset connection (for testing)
     */
    public static function reset(): void
    {
        self::$connection = null;
    }
}
