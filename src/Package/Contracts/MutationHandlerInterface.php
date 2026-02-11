<?php

namespace Hub\Package\Contracts;

/**
 * Mutation Handler Interface
 * 
 * All package mutation handlers MUST implement this interface.
 * This prevents arbitrary class execution and ensures consistent behavior.
 * 
 * Security: Handlers are registered in HandlerRegistry and validated before execution.
 * 
 * @see PACKAGE_ARCHITECTURE_SPEC.md §14 (Handler Registry & Safety)
 */
interface MutationHandlerInterface
{
    /**
     * Execute a mutation with enforcement pipeline
     * 
     * Pipeline steps (automatic, controlled by MutationRouter):
     * 1. Page access check (before this method is called)
     * 2. CSRF validation (before this method is called)
     * 3. Permission check (before this method is called)
     * 4. Input validation (before this method is called)
     * 5. Apply scope filters (passed as $context['scope'])
     * 6. Execute mutation (this method)
     * 7. Audit log (with before/after payloads)
     * 8. Invalidate caches
     * 9. Queue background jobs (if needed)
     * 10. Return response (token for secrets, not raw values)
     * 
     * @param array $input Validated input data from client
     * @param array $context Execution context:
     *   - 'user' => User object (current authenticated user)
     *   - 'scope' => array (row-level security filters to apply)
     *   - 'packageId' => string (e.g., 'com.woodson.student-directory')
     *   - 'mutationName' => string (e.g., 'resetPassword')
     *   - 'correlationId' => string (for audit tracing)
     *   - 'beforeState' => array|null (for audit logging, fetched before mutation)
     * 
     * @return array Mutation result:
     *   [
     *     'success' => bool,         // Required: operation success status
     *     'data' => [...],           // Optional: result data (NEVER raw secrets)
     *     'token' => string,         // Required if dataClass: "secret" involved
     *     'expiresAt' => int,        // Required if token provided (Unix timestamp)
     *     'message' => string        // Optional: user-facing message
     *   ]
     * 
     * CRITICAL: For sensitive actions (dataClass: "secret"):
     * - NEVER return raw secrets in 'data'
     * - Return token instead, client fetches secret via separate endpoint
     * - Token expires in 30-60 seconds, single-use only
     * 
     * @throws \InvalidArgumentException if input is invalid
     * @throws \RuntimeException if mutation execution fails
     */
    public function execute(array $input, array $context): array;

    /**
     * Get mutation metadata for validation
     * 
     * Used by MutationRouter to validate incoming requests before execution.
     * 
     * @return array Mutation definition:
     *   [
     *     'name' => 'resetPassword',
     *     'description' => 'Reset student password',
     *     'requiredPermission' => 'students.manage',  // Required: permission to check
     *     'inputSchema' => [...],     // Optional: JSON schema for input validation
     *     'isSensitive' => true,      // Optional: if true, returns token not data
     *     'invalidatesCaches' => ['students', 'passwords'],  // Optional: cache tags
     *     'backgroundJobs' => ['SendPasswordResetEmail']     // Optional: jobs to queue
     *   ]
     */
    public function getMetadata(): array;
}
