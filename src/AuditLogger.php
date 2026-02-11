<?php

namespace Hub;

/**
 * Audit Logger
 * 
 * Enterprise-grade audit logging with:
 * - Correlation IDs (UUID v4 per request)
 * - Secure IP capture (proxy-aware)
 * - Sanitized error traces (no secrets in DB)
 * - Expanded input sanitization
 * - Before/after state capture
 * 
 * @see AUDIT_SYSTEM_CHANGELOG.md for full specification
 */
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
     * @param array|null $metadata Additional context (correlation_id, execution_time, etc.)
     */
    public static function log(
        string $action,
        string $tableName,
        ?int $recordId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null,
        ?array $metadata = null
    ) {
        try {
            // Get database instance
            $db = Database::getInstance();

            // Get current user if not provided
            if ($userId === null) {
                $currentUser = Auth::getCurrentUser();
                $userId = $currentUser['id'] ?? null;
            }

            // Get correlation ID from request context
            $correlationId = $metadata['correlation_id'] ?? RequestContext::getCorrelationId();

            // Get client IP (proxy-aware)
            $ipAddress = RequestContext::getIpAddress();

            // Get user agent
            $userAgent = RequestContext::getUserAgent();

            // Sanitize values before logging
            $oldValues = $oldValues ? self::sanitizeForLogging($oldValues) : null;
            $newValues = $newValues ? self::sanitizeForLogging($newValues) : null;

            // Convert arrays to JSON
            $oldValuesJson = $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null;
            $newValuesJson = $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null;

            // Extract execution time if provided
            $executionTimeMs = $metadata['execution_time_ms'] ?? null;

            // Extract error info if provided
            $errorMessage = $metadata['error_message'] ?? null;
            $errorHash = $metadata['error_hash'] ?? null;
            $errorClass = $metadata['error_class'] ?? null;
            $errorTopFrames = $metadata['error_top_frames'] ?? null;

            // Insert into audit_log
            $db->execute(
                "INSERT INTO audit_log 
                (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, 
                 correlation_id, execution_time_ms, error_message, error_hash, error_class, error_top_frames)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $userId,
                    strtolower($action),
                    $tableName,
                    $recordId,
                    $oldValuesJson,
                    $newValuesJson,
                    $ipAddress,
                    $userAgent,
                    $correlationId,
                    $executionTimeMs,
                    $errorMessage,
                    $errorHash,
                    $errorClass,
                    $errorTopFrames
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
     * Sanitize data for audit logging
     * 
     * Removes sensitive data that should never appear in logs:
     * - Passwords, tokens, secrets, API keys
     * - Authorization headers, cookies
     * - Session data, refresh tokens, private keys
     * 
     * Handles arrays, objects, and collections.
     * 
     * @param mixed $data Data to sanitize (array, object, or scalar)
     * 
     * @return mixed Sanitized data
     */
    public static function sanitizeForLogging($data)
    {
        // Sensitive key patterns (case-insensitive)
        $sensitiveKeys = [
            'password',
            'token',
            'secret',
            'api_key',
            'apikey',
            'csrf_token',
            'authorization',
            'bearer',
            'cookie',
            'set-cookie',
            'session',
            'refresh_token',
            'id_token',
            'private_key',
            'privatekey',
            'access_token',
            'accesstoken',
        ];

        // Handle objects (convert to array first)
        if (is_object($data)) {
            // Handle Laravel collections
            if (method_exists($data, 'toArray')) {
                $data = $data->toArray();
            } else {
                $data = (array) $data;
            }
        }

        // Handle arrays
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $lowerKey = strtolower((string) $key);

                // Check if key contains sensitive pattern
                $isSensitive = false;
                foreach ($sensitiveKeys as $sensitive) {
                    if (str_contains($lowerKey, $sensitive)) {
                        $isSensitive = true;
                        break;
                    }
                }

                if ($isSensitive) {
                    $data[$key] = '[REDACTED]';
                } elseif (is_array($value) || is_object($value)) {
                    // Recursively sanitize nested structures
                    $data[$key] = self::sanitizeForLogging($value);
                }
            }
        }

        return $data;
    }

    /**
     * Sanitize exception for logging
     * 
     * Security: Full stack traces can contain:
     * - Secrets in function arguments
     * - SQL fragments with sensitive data
     * - Filesystem paths (information disclosure)
     * - Raw inputs with passwords/tokens
     * 
     * Instead of storing full trace in DB:
     * - Store error hash (for deduplication)
     * - Store error class + message
     * - Store top N frames (trimmed)
     * - Full trace goes to file logs (not DB)
     * 
     * @param \Throwable $error Exception to sanitize
     * 
     * @return array Sanitized error metadata
     */
    public static function sanitizeException(\Throwable $error): array
    {
        $trace = $error->getTrace();
        $topFrames = [];

        // Capture top 5 frames only (enough for debugging, minimal risk)
        for ($i = 0; $i < min(5, count($trace)); $i++) {
            $frame = $trace[$i];
            $topFrames[] = [
                'file' => basename($frame['file'] ?? 'unknown'),
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? 'unknown',
                'class' => $frame['class'] ?? null,
            ];
        }

        // Generate hash for deduplication
        $errorHash = hash('sha256', $error->getFile() . ':' . $error->getLine() . ':' . $error->getMessage());

        return [
            'error_message' => substr($error->getMessage(), 0, 500), // Truncate long messages
            'error_class' => get_class($error),
            'error_hash' => $errorHash,
            'error_top_frames' => json_encode($topFrames),
        ];
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
