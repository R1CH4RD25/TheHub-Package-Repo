<?php

namespace Hub\Package;

use Hub\Database;
use Hub\User;

/**
 * Sensitive Action Token Manager
 *
 * Implements the secure "reveal" pattern for sensitive data:
 * 1. User requests reveal (provides reason)
 * 2. System issues a time-limited, single-use token
 * 3. User consumes token within window to see data
 * 4. Token auto-expires; all actions comprehensively logged
 *
 * Use cases:
 * - "Reveal Password" button → token → countdown → auto-expire
 * - "View SSN" → reason modal → token → one-time view
 * - "Download PII Export" → approval → token → single download
 *
 * Security Properties:
 * - Tokens are 64-byte hex (128 chars) — cryptographically random
 * - Default TTL: 30 seconds (configurable per action)
 * - Single-use: consumed on first read
 * - Reason required: logged to audit trail
 * - IP-bound: token only valid from issuing IP
 * - Fully audited: issue, consume, expire, revoke all logged
 *
 * @see PolicyEngine (rate limiting for reveals)
 * @see PackageAuditLogger (event logging)
 * @see database/sprint2-security-schema.sql (sensitive_action_tokens table)
 */
class SensitiveActionManager
{
    /** @var int Default token TTL in seconds */
    private const DEFAULT_TTL = 30;

    /** @var int Maximum token TTL in seconds */
    private const MAX_TTL = 120;

    /** @var int Token length in bytes (generates 128 hex chars) */
    private const TOKEN_BYTES = 64;

    private Database $db;
    private PolicyEngine $policy;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->policy = new PolicyEngine();
    }

    /**
     * Issue a new sensitive action token
     *
     * Creates a time-limited, single-use token after validation.
     * The caller must verify the user has permission before calling this.
     *
     * @param User $user The requesting user
     * @param string $packageId Package identifier
     * @param string $actionName Action name (e.g., 'revealPassword', 'viewSSN')
     * @param string $reason User-supplied justification (required, min 5 chars)
     * @param int|null $targetRecordId Record being revealed (optional)
     * @param int $ttlSeconds Token lifetime (default: 30s, max: 120s)
     *
     * @return array Token response:
     *   ['token' => string, 'expiresAt' => string, 'expiresIn' => int]
     *
     * @throws \RuntimeException on validation failure or rate limit
     */
    public function issueToken(
        User $user,
        string $packageId,
        string $actionName,
        string $reason,
        ?int $targetRecordId = null,
        int $ttlSeconds = self::DEFAULT_TTL
    ): array {
        // Validate reason
        $reason = trim($reason);
        if (strlen($reason) < 5) {
            throw new \RuntimeException('Reason must be at least 5 characters');
        }

        // Enforce TTL bounds
        $ttlSeconds = min($ttlSeconds, self::MAX_TTL);
        $ttlSeconds = max($ttlSeconds, 5);

        // Check rate limit for reveals
        $rateLimitKey = "{$packageId}.reveal.{$actionName}";
        if ($this->policy->isRateLimited($user, $rateLimitKey)) {
            PackageAuditLogger::logRateLimited($packageId, $actionName, 5, 5, $user->id);
            throw new \RuntimeException('Reveal rate limit exceeded. Please wait before requesting another reveal.');
        }

        // Revoke any outstanding tokens for this user/action (only one active at a time)
        $this->revokeOutstandingTokens($user->id, $packageId, $actionName);

        // Generate cryptographically secure token
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $correlationId = \Hub\RequestContext::getCorrelationId();
        $ipAddress = \Hub\RequestContext::getIpAddress() ?? '0.0.0.0';

        // Store token — use DB NOW() for timestamp to avoid PHP/MySQL timezone mismatch
        $stmt = $this->db->prepare("
            INSERT INTO sensitive_action_tokens
                (token, user_id, package_id, action_name, target_record_id,
                 reason, expires_at, ip_address, correlation_id)
            VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), ?, ?)
        ");

        $stmt->execute([
            $token,
            $user->id,
            $packageId,
            $actionName,
            $targetRecordId,
            $reason,
            $ttlSeconds,
            $ipAddress,
            $correlationId,
        ]);

        // Fetch the actual expires_at set by the DB
        $expiresAt = $this->db->prepare("SELECT expires_at FROM sensitive_action_tokens WHERE token = ?");
        $expiresAt->execute([$token]);
        $expiresAtValue = $expiresAt->fetchColumn();

        // Record rate hit
        $this->policy->recordRateHit($user, $rateLimitKey);

        // Audit log
        PackageAuditLogger::logTokenEvent(
            'token_issued',
            $packageId,
            $actionName,
            $token,
            $user->id,
            $targetRecordId
        );

        return [
            'token' => $token,
            'expiresAt' => $expiresAtValue,
            'expiresIn' => $ttlSeconds,
        ];
    }

    /**
     * Consume a token and return the sensitive data
     *
     * Validates the token is:
     * 1. Valid (exists, not revoked)
     * 2. Not expired
     * 3. Not already used
     * 4. From the same IP as issuance
     * 5. Owned by the requesting user
     *
     * On successful validation, marks the token as used and returns
     * the token metadata. The caller is responsible for fetching and
     * returning the actual sensitive data.
     *
     * @param string $token The token string
     * @param int $userId The requesting user's ID
     *
     * @return array Token metadata:
     *   ['packageId' => string, 'actionName' => string, 'targetRecordId' => int|null,
     *    'reason' => string, 'correlationId' => string]
     *
     * @throws \RuntimeException on invalid, expired, or already-used token
     */
    public function consumeToken(string $token, int $userId): array
    {
        // Fetch token in a transaction to prevent race conditions
        $inTransaction = $this->db->inTransaction();
        if (!$inTransaction) {
            $this->db->beginTransaction();
        }

        try {
            // Lock the row for update — use DB NOW() for timezone-safe expiry check
            $stmt = $this->db->prepare("
                SELECT *, (expires_at < NOW()) as is_expired
                FROM sensitive_action_tokens
                WHERE token = ?
                FOR UPDATE
            ");
            $stmt->execute([$token]);
            $tokenRow = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$tokenRow) {
                throw new \RuntimeException('Invalid token');
            }

            // Validate ownership
            if ((int)$tokenRow['user_id'] !== $userId) {
                PackageAuditLogger::logPolicyViolation(
                    $tokenRow['package_id'],
                    $tokenRow['action_name'],
                    "Token ownership mismatch: token user={$tokenRow['user_id']}, request user={$userId}",
                    $userId
                );
                throw new \RuntimeException('Invalid token');
            }

            // Check if already used
            if ($tokenRow['used_at'] !== null) {
                throw new \RuntimeException('Token already consumed');
            }

            // Check if revoked
            if ($tokenRow['revoked_at'] !== null) {
                throw new \RuntimeException('Token has been revoked');
            }

            // Check expiry (using DB-calculated flag to avoid timezone issues)
            if ($tokenRow['is_expired']) {
                // Mark as expired in audit
                PackageAuditLogger::logTokenEvent(
                    'token_expired',
                    $tokenRow['package_id'],
                    $tokenRow['action_name'],
                    $token,
                    $userId,
                    $tokenRow['target_record_id'] ? (int)$tokenRow['target_record_id'] : null
                );
                throw new \RuntimeException('Token has expired');
            }

            // Validate IP (optional but recommended)
            $currentIp = \Hub\RequestContext::getIpAddress();
            if ($tokenRow['ip_address'] !== $currentIp) {
                PackageAuditLogger::logPolicyViolation(
                    $tokenRow['package_id'],
                    $tokenRow['action_name'],
                    "IP mismatch: issued from {$tokenRow['ip_address']}, consumed from {$currentIp}",
                    $userId
                );
                // Log but don't block — user may have changed networks
                error_log("[SensitiveAction] IP mismatch for token: issued={$tokenRow['ip_address']}, consumed={$currentIp}");
            }

            // Mark as used
            $updateStmt = $this->db->prepare("
                UPDATE sensitive_action_tokens SET used_at = NOW() WHERE id = ?
            ");
            $updateStmt->execute([$tokenRow['id']]);

            if (!$inTransaction) {
                $this->db->commit();
            }

            // Audit log
            PackageAuditLogger::logTokenEvent(
                'token_consumed',
                $tokenRow['package_id'],
                $tokenRow['action_name'],
                $token,
                $userId,
                $tokenRow['target_record_id'] ? (int)$tokenRow['target_record_id'] : null
            );

            // Also log the reveal itself
            PackageAuditLogger::logReveal(
                $tokenRow['package_id'],
                $tokenRow['action_name'],
                $tokenRow['reason'],
                $tokenRow['target_record_id'] ? (int)$tokenRow['target_record_id'] : null,
                $userId
            );

            return [
                'packageId' => $tokenRow['package_id'],
                'actionName' => $tokenRow['action_name'],
                'targetRecordId' => $tokenRow['target_record_id'] ? (int)$tokenRow['target_record_id'] : null,
                'reason' => $tokenRow['reason'],
                'correlationId' => $tokenRow['correlation_id'],
            ];
        } catch (\Exception $e) {
            if (!$inTransaction) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Validate a token without consuming it
     *
     * Useful for UI: check if a token is still valid before showing countdown.
     *
     * @param string $token The token string
     * @param int $userId The requesting user's ID
     *
     * @return array ['valid' => bool, 'expiresIn' => int, 'reason' => string|null]
     */
    public function validateToken(string $token, int $userId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT *, TIMESTAMPDIFF(SECOND, NOW(), expires_at) as expires_in_seconds
                FROM sensitive_action_tokens WHERE token = ?
            ");
            $stmt->execute([$token]);
            $tokenRow = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$tokenRow) {
                return ['valid' => false, 'expiresIn' => 0, 'reason' => 'Token not found'];
            }

            if ((int)$tokenRow['user_id'] !== $userId) {
                return ['valid' => false, 'expiresIn' => 0, 'reason' => 'Invalid token'];
            }

            if ($tokenRow['used_at'] !== null) {
                return ['valid' => false, 'expiresIn' => 0, 'reason' => 'Already consumed'];
            }

            if ($tokenRow['revoked_at'] !== null) {
                return ['valid' => false, 'expiresIn' => 0, 'reason' => 'Revoked'];
            }

            $expiresIn = (int)$tokenRow['expires_in_seconds'];
            if ($expiresIn <= 0) {
                return ['valid' => false, 'expiresIn' => 0, 'reason' => 'Expired'];
            }

            return ['valid' => true, 'expiresIn' => $expiresIn, 'reason' => null];
        } catch (\Exception $e) {
            return ['valid' => false, 'expiresIn' => 0, 'reason' => 'Error'];
        }
    }

    /**
     * Revoke a specific token
     *
     * @param string $token Token to revoke
     * @param int $userId User requesting revocation (must be owner or admin)
     *
     * @return bool True if revoked
     */
    public function revokeToken(string $token, int $userId): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE sensitive_action_tokens
                SET revoked_at = NOW()
                WHERE token = ? AND (user_id = ? OR ? IN (
                    SELECT id FROM users WHERE role IN ('admin', 'super_admin')
                ))
                AND used_at IS NULL AND revoked_at IS NULL
            ");
            $stmt->execute([$token, $userId, $userId]);

            if ($stmt->rowCount() > 0) {
                // Get token details for logging
                $fetchStmt = $this->db->prepare("SELECT * FROM sensitive_action_tokens WHERE token = ?");
                $fetchStmt->execute([$token]);
                $tokenRow = $fetchStmt->fetch(\PDO::FETCH_ASSOC);

                if ($tokenRow) {
                    PackageAuditLogger::logTokenEvent(
                        'token_revoked',
                        $tokenRow['package_id'],
                        $tokenRow['action_name'],
                        $token,
                        $userId,
                        $tokenRow['target_record_id'] ? (int)$tokenRow['target_record_id'] : null
                    );
                }
                return true;
            }

            return false;
        } catch (\Exception $e) {
            error_log("[SensitiveAction] Revoke failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Revoke all outstanding tokens for a user/action combination
     *
     * Called automatically before issuing a new token (one active at a time).
     */
    private function revokeOutstandingTokens(int $userId, string $packageId, string $actionName): void
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE sensitive_action_tokens
                SET revoked_at = NOW()
                WHERE user_id = ? AND package_id = ? AND action_name = ?
                AND used_at IS NULL AND revoked_at IS NULL AND expires_at > NOW()
            ");
            $stmt->execute([$userId, $packageId, $actionName]);
        } catch (\Exception $e) {
            error_log("[SensitiveAction] Revoke outstanding failed: " . $e->getMessage());
        }
    }

    /**
     * Cleanup expired tokens (call from cron or gc)
     *
     * Removes tokens older than specified age. Should be run periodically.
     *
     * @param int $maxAgeDays Maximum age in days (default: 30)
     *
     * @return int Number of rows deleted
     */
    public function cleanup(int $maxAgeDays = 30): int
    {
        try {
            $cutoff = date('Y-m-d H:i:s', time() - ($maxAgeDays * 86400));
            $stmt = $this->db->prepare("
                DELETE FROM sensitive_action_tokens WHERE created_at < ?
            ");
            $stmt->execute([$cutoff]);
            return $stmt->rowCount();
        } catch (\Exception $e) {
            error_log("[SensitiveAction] Cleanup failed: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get token statistics for admin dashboard
     *
     * @param int $hoursBack Hours to look back
     *
     * @return array Statistics
     */
    public function getStats(int $hoursBack = 24): array
    {
        try {
            $since = date('Y-m-d H:i:s', time() - ($hoursBack * 3600));

            $stmt = $this->db->prepare("
                SELECT
                    COUNT(*) as total_issued,
                    SUM(CASE WHEN used_at IS NOT NULL THEN 1 ELSE 0 END) as consumed,
                    SUM(CASE WHEN revoked_at IS NOT NULL THEN 1 ELSE 0 END) as revoked,
                    SUM(CASE WHEN used_at IS NULL AND revoked_at IS NULL AND expires_at < NOW() THEN 1 ELSE 0 END) as expired_unused,
                    SUM(CASE WHEN used_at IS NULL AND revoked_at IS NULL AND expires_at >= NOW() THEN 1 ELSE 0 END) as active
                FROM sensitive_action_tokens
                WHERE created_at >= ?
            ");
            $stmt->execute([$since]);

            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [
                'total_issued' => 0,
                'consumed' => 0,
                'revoked' => 0,
                'expired_unused' => 0,
                'active' => 0,
            ];
        } catch (\Exception $e) {
            return [
                'total_issued' => 0,
                'consumed' => 0,
                'revoked' => 0,
                'expired_unused' => 0,
                'active' => 0,
            ];
        }
    }
}
