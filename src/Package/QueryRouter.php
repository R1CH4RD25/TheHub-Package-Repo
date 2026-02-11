<?php

namespace Hub\Package;

use Hub\Package\Contracts\QueryHandlerInterface;
use Hub\Package\PolicyEngine;
use Hub\Package\ScopeEngine;
use Hub\Package\ProjectionEngine;
use Hub\Package\HandlerRegistry;
use Hub\User;
use Hub\AuditLogger;

/**
 * Query Router
 * 
 * Enforces 8-step security pipeline for all package queries.
 * NON-OPTIONAL, NON-DELEGABLE - every query goes through this.
 * 
 * @see PACKAGE_ARCHITECTURE_SPEC.md §12 (Enforcement Pipelines)
 */
class QueryRouter
{
    private PolicyEngine $policy;
    private ScopeEngine $scope;
    private ProjectionEngine $projection;
    private HandlerRegistry $registry;

    public function __construct(
        PolicyEngine $policy,
        ScopeEngine $scope,
        ProjectionEngine $projection,
        HandlerRegistry $registry
    ) {
        $this->policy = $policy;
        $this->scope = $scope;
        $this->projection = $projection;
        $this->registry = $registry;
    }

    /**
     * Execute a query with full enforcement pipeline
     * 
     * 8-STEP PIPELINE (NON-OPTIONAL):
     * 1. Page access check (FIRST - fail fast if user can't access)
     * 2. Validate handler exists
     * 3. Apply scope filters
     * 4. Execute query
     * 5. Apply field masking
     * 6. Audit log
     * 7. Rate limit check
     * 8. Return response
     * 
     * @param User $user Current user
     * @param string $packageId Package identifier (e.g., 'com.woodson.student-directory')
     * @param array $pageConfig Page configuration from package JSON
     * @param string $queryName Query name (e.g., 'listStudents')
     * @param array $params Query parameters from client
     * @param array $dataClassifications Field classifications from PackageValidator
     * 
     * @return array Query result (masked, scoped, audited)
     * 
     * @throws \RuntimeException if any security check fails
     */
    public function execute(
        User $user,
        string $packageId,
        array $pageConfig,
        string $queryName,
        array $params = [],
        array $dataClassifications = []
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

            // STEP 2: Validate handler exists
            $handler = $this->registry->getQuery($packageId, $queryName);
            if (!$handler) {
                throw new \RuntimeException(
                    "Query handler not found: {$packageId}::{$queryName}"
                );
            }

            // Validate handler interface
            if (!($handler instanceof QueryHandlerInterface)) {
                throw new \RuntimeException(
                    "Query handler must implement QueryHandlerInterface"
                );
            }

            // Check query metadata for additional permissions
            $metadata = $handler->getMetadata();
            if (isset($metadata['requiredPermission'])) {
                if (!$this->policy->check($user, $metadata['requiredPermission'])) {
                    throw new \RuntimeException(
                        "Access denied: Missing permission '{$metadata['requiredPermission']}'"
                    );
                }
            }

            // STEP 3: Apply scope filters
            $scopeFilters = $this->policy->getScopeFilters($user, $pageConfig['scope'] ?? []);
            
            // Build execution context
            $context = [
                'user' => $user,
                'scope' => $scopeFilters,
                'packageId' => $packageId,
                'queryName' => $queryName,
                'correlationId' => $correlationId,
            ];

            // STEP 4: Execute query
            $result = $handler->execute($params, $context);

            // STEP 5: Apply field masking
            $fieldMasks = $this->policy->getFieldMasks($user, $dataClassifications);
            $result = $this->projection->maskFields($result, $fieldMasks, $dataClassifications);

            // STEP 6: Audit log
            $this->logQuery($user, $packageId, $queryName, $params, $correlationId, $startTime);

            // STEP 7: Rate limit check (warning only for queries, not blocking)
            if ($this->policy->isRateLimited($user, "{$packageId}.query.{$queryName}", [])) {
                // Log warning but don't block reads
                error_log("WARNING: Rate limit exceeded for {$user->id} on {$packageId}.query.{$queryName}");
            }

            // STEP 8: Return response
            return [
                'success' => true,
                'data' => $result,
                'meta' => [
                    'correlationId' => $correlationId,
                    'executionTime' => microtime(true) - $startTime,
                ]
            ];

        } catch (\Exception $e) {
            // Log error
            $this->logQueryError($user, $packageId, $queryName, $params, $correlationId, $e);

            // Re-throw
            throw new \RuntimeException(
                "Query execution failed: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Log successful query execution
     * 
     * @param User $user Current user
     * @param string $packageId Package ID
     * @param string $queryName Query name
     * @param array $params Query parameters
     * @param string $correlationId Correlation ID
     * @param float $startTime Start timestamp
     */
    private function logQuery(
        User $user,
        string $packageId,
        string $queryName,
        array $params,
        string $correlationId,
        float $startTime
    ): void {
        $executionTime = microtime(true) - $startTime;

        AuditLogger::log(
            'package_query',
            'query',
            null, // No specific record ID for queries
            [
                'event' => "package.{$packageId}.query.{$queryName}",
                'user_id' => $user->id,
                'package_id' => $packageId,
                'query_name' => $queryName,
                'parameters' => json_encode($params),
                'correlation_id' => $correlationId,
                'execution_time_ms' => round($executionTime * 1000, 2),
                'ip_address' => \Hub\RequestContext::getIpAddress(),
            ]
        );
    }

    /**
     * Log query execution error
     * 
     * @param User $user Current user
     * @param string $packageId Package ID
     * @param string $queryName Query name
     * @param array $params Query parameters
     * @param string $correlationId Correlation ID
     * @param \Exception $error Exception that occurred
     */
    private function logQueryError(
        User $user,
        string $packageId,
        string $queryName,
        array $params,
        string $correlationId,
        \Exception $error
    ): void {
        AuditLogger::log(
            'package_query_error',
            'query',
            null,
            [
                'event' => "package.{$packageId}.query.{$queryName}.error",
                'user_id' => $user->id,
                'package_id' => $packageId,
                'query_name' => $queryName,
                'parameters' => json_encode($params),
                'correlation_id' => $correlationId,
                ...\Hub\AuditLogger::sanitizeException($error),
                'ip_address' => \Hub\RequestContext::getIpAddress(),
            ]
        );
    }

    /**
     * Generate correlation ID for request tracing
     * 
     * Uses RequestContext for UUID v4 generation (better than uniqid)
     * 
     * @return string UUID v4 correlation ID
     */
    private function generateCorrelationId(): string
    {
        return \Hub\RequestContext::getCorrelationId();
    }
}
