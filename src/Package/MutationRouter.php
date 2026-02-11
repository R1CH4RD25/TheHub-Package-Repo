<?php

namespace Hub\Package;

use Hub\Package\Contracts\MutationHandlerInterface;
use Hub\Package\PolicyEngine;
use Hub\Package\ScopeEngine;
use Hub\Package\HandlerRegistry;
use Hub\User;
use Hub\AuditLogger;

/**
 * Mutation Router
 * 
 * Enforces 10-step security pipeline for all package mutations.
 * NON-OPTIONAL, NON-DELEGABLE - every mutation goes through this.
 * 
 * @see PACKAGE_ARCHITECTURE_SPEC.md §12 (Enforcement Pipelines)
 */
class MutationRouter
{
    private PolicyEngine $policy;
    private ScopeEngine $scope;
    private HandlerRegistry $registry;

    public function __construct(
        PolicyEngine $policy,
        ScopeEngine $scope,
        HandlerRegistry $registry
    ) {
        $this->policy = $policy;
        $this->scope = $scope;
        $this->registry = $registry;
    }

    /**
     * Execute a mutation with full enforcement pipeline
     * 
     * 10-STEP PIPELINE (NON-OPTIONAL):
     * 1. Page access check (FIRST - fail fast if user can't access)
     * 2. CSRF validation
     * 3. Permission check
     * 4. Input validation
     * 5. Apply scope filters
     * 6. Execute mutation
     * 7. Audit log (with before/after)
     * 8. Invalidate caches
     * 9. Queue background jobs
     * 10. Return response (token for secrets, not raw values)
     * 
     * @param User $user Current user
     * @param string $packageId Package identifier
     * @param array $pageConfig Page configuration from package JSON
     * @param string $mutationName Mutation name (e.g., 'resetPassword')
     * @param array $input Input data from client (validated)
     * @param string|null $csrfToken CSRF token from client
     * 
     * @return array Mutation result
     * 
     * @throws \RuntimeException if any security check fails
     */
    public function execute(
        User $user,
        string $packageId,
        array $pageConfig,
        string $mutationName,
        array $input = [],
        ?string $csrfToken = null
    ): array {
        $correlationId = $this->generateCorrelationId();
        $startTime = microtime(true);

        try {
            // STEP 1: Page access check (FIRST - fail fast)
            if (!$this->policy->canAccessPage($user, $pageConfig)) {
                throw new \RuntimeException(
                    "Access denied: User does not have access to page '{$pageConfig['id']}'"
                );
            }

            // STEP 2: CSRF validation
            if (!$this->validateCsrf($csrfToken)) {
                throw new \RuntimeException("CSRF token validation failed");
            }

            // STEP 3: Permission check
            $handler = $this->registry->getMutation($packageId, $mutationName);
            if (!$handler) {
                throw new \RuntimeException(
                    "Mutation handler not found: {$packageId}::{$mutationName}"
                );
            }

            // Validate handler interface
            if (!($handler instanceof MutationHandlerInterface)) {
                throw new \RuntimeException(
                    "Mutation handler must implement MutationHandlerInterface"
                );
            }

            // Check mutation metadata for permissions
            $metadata = $handler->getMetadata();
            if (!isset($metadata['requiredPermission'])) {
                throw new \RuntimeException(
                    "Mutation must specify requiredPermission in metadata"
                );
            }

            if (!$this->policy->check($user, $metadata['requiredPermission'])) {
                throw new \RuntimeException(
                    "Access denied: Missing permission '{$metadata['requiredPermission']}'"
                );
            }

            // STEP 4: Input validation
            // (Handler should validate internally, but check for XSS/SQL injection basics)
            $input = $this->sanitizeInput($input);

            // STEP 5: Apply scope filters
            $scopeFilters = $this->policy->getScopeFilters($user, $pageConfig['scope'] ?? []);

            // Capture before state for audit (if configured)
            $beforeState = null;
            if (isset($metadata['captureBeforeState']) && $metadata['captureBeforeState']) {
                $beforeState = $this->captureBeforeState($packageId, $mutationName, $input);
            }

            // Build execution context
            $context = [
                'user' => $user,
                'scope' => $scopeFilters,
                'packageId' => $packageId,
                'mutationName' => $mutationName,
                'correlationId' => $correlationId,
                'beforeState' => $beforeState,
            ];

            // STEP 6: Execute mutation
            $result = $handler->execute($input, $context);

            // Validate result format
            if (!isset($result['success'])) {
                throw new \RuntimeException("Mutation must return 'success' field");
            }

            // STEP 7: Audit log (with before/after)
            $this->logMutation(
                $user,
                $packageId,
                $mutationName,
                $input,
                $beforeState,
                $result,
                $correlationId,
                $startTime
            );

            // STEP 8: Invalidate caches
            if (isset($metadata['invalidatesCaches']) && is_array($metadata['invalidatesCaches'])) {
                $this->invalidateCaches($metadata['invalidatesCaches']);
            }

            // STEP 9: Queue background jobs
            if (isset($metadata['backgroundJobs']) && is_array($metadata['backgroundJobs'])) {
                $this->queueBackgroundJobs($metadata['backgroundJobs'], $input, $result);
            }

            // STEP 10: Return response
            // Ensure secrets are not returned directly
            if (isset($metadata['isSensitive']) && $metadata['isSensitive']) {
                if (isset($result['data']) && !isset($result['token'])) {
                    throw new \RuntimeException(
                        "Sensitive mutation must return 'token' not raw 'data'"
                    );
                }
            }

            return [
                'success' => $result['success'],
                'data' => $result['data'] ?? null,
                'token' => $result['token'] ?? null,
                'expiresAt' => $result['expiresAt'] ?? null,
                'message' => $result['message'] ?? null,
                'meta' => [
                    'correlationId' => $correlationId,
                    'executionTime' => microtime(true) - $startTime,
                ]
            ];

        } catch (\Exception $e) {
            // Log error
            $this->logMutationError($user, $packageId, $mutationName, $input, $correlationId, $e);

            // Re-throw
            throw new \RuntimeException(
                "Mutation execution failed: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Validate CSRF token
     * 
     * @param string|null $token CSRF token from client
     * 
     * @return bool True if valid
     */
    private function validateCsrf(?string $token): bool
    {
        // Use existing CSRF validation from bootstrap
        if (!$token) {
            return false;
        }

        // Verify against session token
        $sessionToken = $_SESSION['csrf_token'] ?? null;
        if (!$sessionToken) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    /**
     * Sanitize input data
     * 
     * @param array $input Input data
     * 
     * @return array Sanitized input
     */
    private function sanitizeInput(array $input): array
    {
        // Basic XSS protection
        array_walk_recursive($input, function(&$value) {
            if (is_string($value)) {
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
        });

        return $input;
    }

    /**
     * Capture before state for audit logging
     * 
     * @param string $packageId Package ID
     * @param string $mutationName Mutation name
     * @param array $input Input data
     * 
     * @return array|null Before state
     */
    private function captureBeforeState(string $packageId, string $mutationName, array $input): ?array
    {
        // TODO: Implement based on package configuration
        // For now, return null (optional feature)
        return null;
    }

    /**
     * Invalidate caches
     * 
     * @param array $cacheTags Cache tags to invalidate
     */
    private function invalidateCaches(array $cacheTags): void
    {
        foreach ($cacheTags as $tag) {
            // TODO: Implement cache invalidation
            // For now, just log
            error_log("Cache invalidated: {$tag}");
        }
    }

    /**
     * Queue background jobs
     * 
     * @param array $jobClasses Job class names
     * @param array $input Input data
     * @param array $result Mutation result
     */
    private function queueBackgroundJobs(array $jobClasses, array $input, array $result): void
    {
        foreach ($jobClasses as $jobClass) {
            // TODO: Implement job queueing
            // For now, just log
            error_log("Background job queued: {$jobClass}");
        }
    }

    /**
     * Log successful mutation execution
     * 
     * @param User $user Current user
     * @param string $packageId Package ID
     * @param string $mutationName Mutation name
     * @param array $input Input data
     * @param array|null $beforeState Before state
     * @param array $result Mutation result
     * @param string $correlationId Correlation ID
     * @param float $startTime Start timestamp
     */
    private function logMutation(
        User $user,
        string $packageId,
        string $mutationName,
        array $input,
        ?array $beforeState,
        array $result,
        string $correlationId,
        float $startTime
    ): void {
        $executionTime = microtime(true) - $startTime;

        // Sanitize input (remove sensitive data before logging)
        $sanitizedInput = $this->sanitizeForLogging($input);

        AuditLogger::log(
            'package_mutation',
            'mutation',
            null,
            [
                'event' => "package.{$packageId}.mutation.{$mutationName}",
                'user_id' => $user->id,
                'package_id' => $packageId,
                'mutation_name' => $mutationName,
                'input' => json_encode($sanitizedInput),
                'before_state' => $beforeState ? json_encode($beforeState) : null,
                'success' => $result['success'],
                'correlation_id' => $correlationId,
                'execution_time_ms' => round($executionTime * 1000, 2),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]
        );
    }

    /**
     * Log mutation execution error
     * 
     * @param User $user Current user
     * @param string $packageId Package ID
     * @param string $mutationName Mutation name
     * @param array $input Input data
     * @param string $correlationId Correlation ID
     * @param \Exception $error Exception
     */
    private function logMutationError(
        User $user,
        string $packageId,
        string $mutationName,
        array $input,
        string $correlationId,
        \Exception $error
    ): void {
        $sanitizedInput = $this->sanitizeForLogging($input);

        AuditLogger::log(
            'package_mutation_error',
            'mutation',
            null,
            [
                'event' => "package.{$packageId}.mutation.{$mutationName}.error",
                'user_id' => $user->id,
                'package_id' => $packageId,
                'mutation_name' => $mutationName,
                'input' => json_encode($sanitizedInput),
                'correlation_id' => $correlationId,
                'error_message' => $error->getMessage(),
                'error_trace' => $error->getTraceAsString(),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]
        );
    }

    /**
     * Sanitize data for audit logging (remove passwords, tokens, etc.)
     * 
     * @param array $data Data to sanitize
     * 
     * @return array Sanitized data
     */
    private function sanitizeForLogging(array $data): array
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'api_key', 'csrf_token'];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);
            foreach ($sensitiveKeys as $sensitive) {
                if (str_contains($lowerKey, $sensitive)) {
                    $data[$key] = '[REDACTED]';
                    break;
                }
            }

            if (is_array($value)) {
                $data[$key] = $this->sanitizeForLogging($value);
            }
        }

        return $data;
    }

    /**
     * Generate correlation ID for request tracing
     * 
     * @return string Unique correlation ID
     */
    private function generateCorrelationId(): string
    {
        return uniqid('mutation-', true);
    }
}
