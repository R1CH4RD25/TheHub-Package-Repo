<?php

namespace Hub;

class AuditLogger
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Log any action to the audit_log table
     *
     * @param string $action The action performed (create, update, delete, approve, login, submit, etc.)
     * @param string $tableName The table/entity affected
     * @param int|null $recordId The ID of the record affected (null for non-record actions)
     * @param array|null $oldValues The values before the change (for updates/deletes)
     * @param array|null $newValues The values after the change (for creates/updates)
     * @param int|null $userId The user who performed the action (null for system actions)
     */
    public static function log(
        string $action,
        string $tableName,
        ?int $recordId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null
    ) {
        try {
            // Get database instance
            $db = Database::getInstance();

            // Get current user if not provided
            if ($userId === null) {
                $currentUser = Auth::getCurrentUser();
                $userId = $currentUser['id'] ?? null;
            }

            // Get client IP
            $ipAddress = self::getClientIp();

            // Get user agent
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            if ($userAgent && strlen($userAgent) > 255) {
                $userAgent = substr($userAgent, 0, 255);
            }

            // Convert arrays to JSON
            $oldValuesJson = $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null;
            $newValuesJson = $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null;

            // Insert into audit_log
            $db->execute(
                "INSERT INTO audit_log (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $userId,
                    strtolower($action),
                    $tableName,
                    $recordId,
                    $oldValuesJson,
                    $newValuesJson,
                    $ipAddress,
                    $userAgent
                ]
            );

            return true;
        } catch (\Exception $e) {
            // Log the error but don't fail the main operation
            error_log("Audit log failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the real client IP address
     */
    private static function getClientIp(): ?string
    {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];

        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle multiple IPs (take the first one)
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return null;
    }

    /**
     * Log a login attempt
     */
    public function logLogin(int $userId, string $email, bool $success = true)
    {
        $this->log(
            $success ? 'login_success' : 'login_failed',
            'users',
            $userId,
            null,
            ['email' => $email, 'success' => $success],
            $userId
        );
    }

    /**
     * Log a logout
     */
    public function logLogout(int $userId)
    {
        $this->log(
            'logout',
            'users',
            $userId,
            null,
            null,
            $userId
        );
    }

    /**
     * Quick log for common patterns
     */
    public static function logCreate(string $table, int $id, array $data, ?int $userId = null)
    {
        $logger = new self();
        return $logger->log('create', $table, $id, null, $data, $userId);
    }

    public static function logUpdate(string $table, int $id, array $oldData, array $newData, ?int $userId = null)
    {
        $logger = new self();
        return $logger->log('update', $table, $id, $oldData, $newData, $userId);
    }

    public static function logDelete(string $table, int $id, array $data, ?int $userId = null)
    {
        $logger = new self();
        return $logger->log('delete', $table, $id, $data, null, $userId);
    }
}
