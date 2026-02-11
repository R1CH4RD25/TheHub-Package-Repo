<?php

namespace Hub\Package\Contracts;

/**
 * Query Handler Interface
 * 
 * All package query handlers MUST implement this interface.
 * This prevents arbitrary class execution and ensures consistent behavior.
 * 
 * Security: Handlers are registered in HandlerRegistry and validated before execution.
 * 
 * @see PACKAGE_ARCHITECTURE_SPEC.md §14 (Handler Registry & Safety)
 */
interface QueryHandlerInterface
{
    /**
     * Execute a query with enforcement pipeline
     * 
     * Pipeline steps (automatic, controlled by QueryRouter):
     * 1. Page access check (before this method is called)
     * 2. Validate handler exists (this interface proves it)
     * 3. Apply scope filters (passed as $context['scope'])
     * 4. Execute query (this method)
     * 5. Apply field masking (after this method returns)
     * 6. Audit log
     * 7. Rate limit check
     * 8. Return response
     * 
     * @param array $params Query parameters from client (filtered, validated)
     * @param array $context Execution context:
     *   - 'user' => User object (current authenticated user)
     *   - 'scope' => array (row-level security filters to apply)
     *   - 'packageId' => string (e.g., 'com.woodson.student-directory')
     *   - 'queryName' => string (e.g., 'listStudents')
     *   - 'correlationId' => string (for audit tracing)
     * 
     * @return array Query result with metadata:
     *   [
     *     'data' => [...],           // Required: query results (will be masked by ProjectionEngine)
     *     'meta' => [                // Optional: pagination, sorting info
     *       'total' => int,
     *       'page' => int,
     *       'perPage' => int
     *     ]
     *   ]
     * 
     * @throws \InvalidArgumentException if params are invalid
     * @throws \RuntimeException if query execution fails
     */
    public function execute(array $params, array $context): array;

    /**
     * Get query metadata for validation
     * 
     * Used by QueryRouter to validate incoming requests before execution.
     * 
     * @return array Query definition:
     *   [
     *     'name' => 'listStudents',
     *     'description' => 'List all students with filters',
     *     'parameters' => [           // Optional: expected parameters
     *       'search' => ['type' => 'string', 'required' => false],
     *       'grade' => ['type' => 'integer', 'required' => false]
     *     ],
     *     'requiredPermission' => 'students.view'  // Optional: additional permission check
     *   ]
     */
    public function getMetadata(): array;
}
