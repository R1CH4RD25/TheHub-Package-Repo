<?php

namespace Hub\Package;

use Hub\Database;
use Hub\AuditLogger;

/**
 * Package Audit Logger
 *
 * Dedicated high-volume audit logging for package events.
 * Writes to `package_audit_events` table (separate from main audit_log).
 * Integrates with PolicyEngine alert rules for anomaly detection.
 *
 * Event Types:
 * - query: Data read operations
 * - mutation: Data write operations
 * - access_denied: Permission/role check failures
 * - rate_limited: Rate limit exceeded events
 * - reveal: Sensitive data reveal actions
 * - policy_violation: Policy rule violations
 * - token_issued: Sensitive action token created
 * - token_consumed: Sensitive action token used
 * - token_expired: Sensitive action token expired unused
 *
 * @see PolicyEngine::checkAlertRules()
 * @see database/sprint2-security-schema.sql
 */
class PackageAuditLogger
{
    private static ?Database $db = null;

    /**
     * Log a package event
     *
     * @param string $eventType Event type (query, mutation, access_denied, etc.)
     * @param string $packageId Package identifier
     * @param string $actionName Action name (e.g., listStudents, resetPassword)
     * @param array $options Additional options:
     *   'userId' => int|null,
     *   'targetRecordId' => int|null,
     *   'severity' => 'info'|'warning'|'critical'|'alert',
     *   'outcome' => 'success'|'denied'|'error'|'rate_limited',
     *   'details' => array (JSON-serializable),
     *   'correlationId' => string,
     *   'executionTimeMs' => float,
     *   'checkAlerts' => bool (default: true),
     */
    public static function log(
        string $eventType,
        string $packageId,
        string $actionName,
        array $options = []
    ): void {
        try {
            $db = self::getDb();

            $userId = $options['userId'] ?? ($_SESSION['user_id'] ?? null);
            $severity = $options['severity'] ?? self::inferSeverity($eventType, $options['outcome'] ?? 'success');
            $outcome = $options['outcome'] ?? 'success';
            $details = isset($options['details']) ? json_encode($options['details']) : null;
            $correlationId = $options['correlationId'] ?? \Hub\RequestContext::getCorrelationId();
            $executionTimeMs = $options['executionTimeMs'] ?? null;
            $ipAddress = $options['ipAddress'] ?? \Hub\RequestContext::getIpAddress();
            $userAgent = $options['userAgent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? null);

            $stmt = $db->prepare("
                INSERT INTO package_audit_events
                    (event_type, package_id, action_name, user_id, target_record_id,
                     severity, outcome, details, ip_address, user_agent,
                     correlation_id, execution_time_ms)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $eventType,
                $packageId,
                $actionName,
                $userId,
                $options['targetRecordId'] ?? null,
                $severity,
                $outcome,
                $details,
                $ipAddress,
                $userAgent ? substr($userAgent, 0, 500) : null,
                $correlationId,
                $executionTimeMs,
            ]);

            // Check alert rules (unless explicitly disabled)
            if ($options['checkAlerts'] ?? true) {
                $policy = new PolicyEngine();
                $policy->checkAlertRules($eventType, $packageId, $userId);
            }
        } catch (\Exception $e) {
            // Never let audit logging break the application
            error_log("[PackageAuditLogger] Failed to log event: " . $e->getMessage());
        }
    }

    // =========================================================================
    // CONVENIENCE METHODS
    // =========================================================================

    /**
     * Log a successful query
     */
    public static function logQuery(
        string $packageId,
        string $queryName,
        array $params = [],
        float $executionTimeMs = 0,
        ?int $rowCount = null
    ): void {
        self::log('query', $packageId, $queryName, [
            'outcome' => 'success',
            'executionTimeMs' => $executionTimeMs,
            'details' => [
                'parameters' => AuditLogger::sanitizeForLogging($params),
                'rowCount' => $rowCount,
            ],
        ]);
    }

    /**
     * Log a successful mutation
     */
    public static function logMutation(
        string $packageId,
        string $mutationName,
        array $input = [],
        ?array $beforeState = null,
        ?array $afterState = null,
        float $executionTimeMs = 0,
        ?int $targetRecordId = null
    ): void {
        self::log('mutation', $packageId, $mutationName, [
            'outcome' => 'success',
            'targetRecordId' => $targetRecordId,
            'executionTimeMs' => $executionTimeMs,
            'details' => [
                'input' => AuditLogger::sanitizeForLogging($input),
                'before' => $beforeState ? AuditLogger::sanitizeForLogging($beforeState) : null,
                'after' => $afterState ? AuditLogger::sanitizeForLogging($afterState) : null,
            ],
        ]);
    }

    /**
     * Log an access denied event
     */
    public static function logAccessDenied(
        string $packageId,
        string $actionName,
        string $reason,
        ?int $userId = null
    ): void {
        self::log('access_denied', $packageId, $actionName, [
            'userId' => $userId,
            'severity' => 'warning',
            'outcome' => 'denied',
            'details' => ['reason' => $reason],
        ]);
    }

    /**
     * Log a rate limit exceeded event
     */
    public static function logRateLimited(
        string $packageId,
        string $actionName,
        int $limit,
        int $used,
        ?int $userId = null
    ): void {
        self::log('rate_limited', $packageId, $actionName, [
            'userId' => $userId,
            'severity' => 'warning',
            'outcome' => 'rate_limited',
            'details' => ['limit' => $limit, 'used' => $used],
        ]);
    }

    /**
     * Log a sensitive data reveal
     */
    public static function logReveal(
        string $packageId,
        string $actionName,
        string $reason,
        ?int $targetRecordId = null,
        ?int $userId = null
    ): void {
        self::log('reveal', $packageId, $actionName, [
            'userId' => $userId,
            'severity' => 'critical',
            'outcome' => 'success',
            'targetRecordId' => $targetRecordId,
            'details' => ['reason' => $reason],
        ]);
    }

    /**
     * Log a policy violation
     */
    public static function logPolicyViolation(
        string $packageId,
        string $actionName,
        string $violation,
        ?int $userId = null
    ): void {
        self::log('policy_violation', $packageId, $actionName, [
            'userId' => $userId,
            'severity' => 'critical',
            'outcome' => 'denied',
            'details' => ['violation' => $violation],
        ]);
    }

    /**
     * Log a token lifecycle event
     */
    public static function logTokenEvent(
        string $eventType,
        string $packageId,
        string $actionName,
        string $tokenId,
        ?int $userId = null,
        ?int $targetRecordId = null
    ): void {
        self::log($eventType, $packageId, $actionName, [
            'userId' => $userId,
            'severity' => $eventType === 'token_consumed' ? 'critical' : 'info',
            'outcome' => 'success',
            'targetRecordId' => $targetRecordId,
            'details' => ['tokenId' => substr($tokenId, 0, 8) . '...'],
        ]);
    }

    // =========================================================================
    // QUERY METHODS
    // =========================================================================

    /**
     * Get recent audit events for a package
     *
     * @param string $packageId Package ID
     * @param int $limit Max events to return
     * @param string|null $eventType Filter by event type
     *
     * @return array Audit events
     */
    public static function getRecentEvents(
        string $packageId,
        int $limit = 50,
        ?string $eventType = null
    ): array {
        try {
            $db = self::getDb();

            $sql = "SELECT * FROM package_audit_events WHERE package_id = ?";
            $params = [$packageId];

            if ($eventType) {
                $sql .= " AND event_type = ?";
                $params[] = $eventType;
            }

            $sql .= " ORDER BY created_at DESC LIMIT ?";
            $params[] = $limit;

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("[PackageAuditLogger] getRecentEvents failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get event counts by type for a package (for dashboard)
     *
     * @param string $packageId Package ID
     * @param int $hoursBack Hours to look back
     *
     * @return array ['query' => 150, 'mutation' => 23, 'access_denied' => 2, ...]
     */
    public static function getEventCounts(string $packageId, int $hoursBack = 24): array
    {
        try {
            $db = self::getDb();
            $since = date('Y-m-d H:i:s', time() - ($hoursBack * 3600));

            $stmt = $db->prepare("
                SELECT event_type, COUNT(*) as count
                FROM package_audit_events
                WHERE package_id = ? AND created_at >= ?
                GROUP BY event_type
                ORDER BY count DESC
            ");
            $stmt->execute([$packageId, $since]);

            $counts = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $counts[$row['event_type']] = (int)$row['count'];
            }
            return $counts;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get user activity summary for a package
     *
     * @param int $userId User ID
     * @param string|null $packageId Optional package filter
     * @param int $hoursBack Hours to look back
     *
     * @return array Activity summary
     */
    public static function getUserActivity(int $userId, ?string $packageId = null, int $hoursBack = 24): array
    {
        try {
            $db = self::getDb();
            $since = date('Y-m-d H:i:s', time() - ($hoursBack * 3600));

            $sql = "
                SELECT event_type, outcome, COUNT(*) as count
                FROM package_audit_events
                WHERE user_id = ? AND created_at >= ?
            ";
            $params = [$userId, $since];

            if ($packageId) {
                $sql .= " AND package_id = ?";
                $params[] = $packageId;
            }

            $sql .= " GROUP BY event_type, outcome ORDER BY count DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get unresolved triggered alerts
     *
     * @return array Unresolved alerts with rule info
     */
    public static function getUnresolvedAlerts(): array
    {
        try {
            $db = self::getDb();

            $stmt = $db->query("
                SELECT pta.*, par.rule_name, par.severity, par.action
                FROM package_triggered_alerts pta
                JOIN package_alert_rules par ON pta.rule_id = par.id
                WHERE pta.resolved_at IS NULL
                ORDER BY pta.created_at DESC
                LIMIT 100
            ");

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Infer severity from event type and outcome
     */
    private static function inferSeverity(string $eventType, string $outcome): string
    {
        if ($outcome === 'denied' || $outcome === 'error') {
            return 'warning';
        }

        return match ($eventType) {
            'access_denied' => 'warning',
            'rate_limited' => 'warning',
            'reveal' => 'critical',
            'policy_violation' => 'critical',
            'token_consumed' => 'critical',
            default => 'info',
        };
    }

    /**
     * Get database connection (lazy-loaded singleton)
     */
    private static function getDb(): Database
    {
        if (self::$db === null) {
            self::$db = Database::getInstance();
        }
        return self::$db;
    }
}
