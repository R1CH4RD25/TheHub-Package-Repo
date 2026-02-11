<?php

namespace Hub\Package;

use Hub\Package\Contracts\QueryHandlerInterface;
use Hub\Package\Contracts\MutationHandlerInterface;

/**
 * Handler Registry
 * 
 * Whitelists package handlers to prevent arbitrary class execution.
 * Validates that handlers implement required interfaces.
 * 
 * Security: Blocks reflection abuse, ensures interface compliance.
 * 
 * @see PACKAGE_ARCHITECTURE_SPEC.md §14 (Handler Registry & Safety)
 */
class HandlerRegistry
{
    /** @var array<string, QueryHandlerInterface> Registered query handlers */
    private array $queryHandlers = [];

    /** @var array<string, MutationHandlerInterface> Registered mutation handlers */
    private array $mutationHandlers = [];

    /** @var array Blocked namespaces (prevent arbitrary class loading) */
    private const BLOCKED_NAMESPACES = [
        'ReflectionClass',
        'ReflectionMethod',
        'ReflectionFunction',
        'PDO',
        'mysqli',
        'eval',
        'exec',
        'shell_exec',
        'system',
        'passthru',
        'proc_open',
    ];

    /**
     * Register a query handler
     * 
     * @param string $packageId Package identifier (e.g., 'com.woodson.student-directory')
     * @param string $queryName Query name (e.g., 'listStudents')
     * @param string $handlerClass Fully-qualified class name
     * 
     * @throws \InvalidArgumentException if handler doesn't implement QueryHandlerInterface
     * @throws \RuntimeException if handler is from blocked namespace
     */
    public function registerQuery(string $packageId, string $queryName, string $handlerClass): void
    {
        $this->validateClass($handlerClass);

        if (!is_subclass_of($handlerClass, QueryHandlerInterface::class)) {
            throw new \InvalidArgumentException(
                "Query handler {$handlerClass} must implement QueryHandlerInterface"
            );
        }

        $key = $this->makeKey($packageId, $queryName);
        $this->queryHandlers[$key] = new $handlerClass();
    }

    /**
     * Register a mutation handler
     * 
     * @param string $packageId Package identifier
     * @param string $mutationName Mutation name (e.g., 'resetPassword')
     * @param string $handlerClass Fully-qualified class name
     * 
     * @throws \InvalidArgumentException if handler doesn't implement MutationHandlerInterface
     * @throws \RuntimeException if handler is from blocked namespace
     */
    public function registerMutation(string $packageId, string $mutationName, string $handlerClass): void
    {
        $this->validateClass($handlerClass);

        if (!is_subclass_of($handlerClass, MutationHandlerInterface::class)) {
            throw new \InvalidArgumentException(
                "Mutation handler {$handlerClass} must implement MutationHandlerInterface"
            );
        }

        $key = $this->makeKey($packageId, $mutationName);
        $this->mutationHandlers[$key] = new $handlerClass();
    }

    /**
     * Get a query handler
     * 
     * @param string $packageId Package identifier
     * @param string $queryName Query name
     * 
     * @return QueryHandlerInterface|null Handler instance or null if not found
     */
    public function getQuery(string $packageId, string $queryName): ?QueryHandlerInterface
    {
        $key = $this->makeKey($packageId, $queryName);
        return $this->queryHandlers[$key] ?? null;
    }

    /**
     * Get a mutation handler
     * 
     * @param string $packageId Package identifier
     * @param string $mutationName Mutation name
     * 
     * @return MutationHandlerInterface|null Handler instance or null if not found
     */
    public function getMutation(string $packageId, string $mutationName): ?MutationHandlerInterface
    {
        $key = $this->makeKey($packageId, $mutationName);
        return $this->mutationHandlers[$key] ?? null;
    }

    /**
     * Check if a query handler is registered
     * 
     * @param string $packageId Package identifier
     * @param string $queryName Query name
     * 
     * @return bool True if handler exists
     */
    public function hasQuery(string $packageId, string $queryName): bool
    {
        $key = $this->makeKey($packageId, $queryName);
        return isset($this->queryHandlers[$key]);
    }

    /**
     * Check if a mutation handler is registered
     * 
     * @param string $packageId Package identifier
     * @param string $mutationName Mutation name
     * 
     * @return bool True if handler exists
     */
    public function hasMutation(string $packageId, string $mutationName): bool
    {
        $key = $this->makeKey($packageId, $mutationName);
        return isset($this->mutationHandlers[$key]);
    }

    /**
     * Get all registered query handlers for a package
     * 
     * @param string $packageId Package identifier
     * 
     * @return array<string, QueryHandlerInterface> Query name => handler map
     */
    public function getPackageQueries(string $packageId): array
    {
        $prefix = $packageId . ':';
        $handlers = [];

        foreach ($this->queryHandlers as $key => $handler) {
            if (str_starts_with($key, $prefix)) {
                $queryName = substr($key, strlen($prefix));
                $handlers[$queryName] = $handler;
            }
        }

        return $handlers;
    }

    /**
     * Get all registered mutation handlers for a package
     * 
     * @param string $packageId Package identifier
     * 
     * @return array<string, MutationHandlerInterface> Mutation name => handler map
     */
    public function getPackageMutations(string $packageId): array
    {
        $prefix = $packageId . ':';
        $handlers = [];

        foreach ($this->mutationHandlers as $key => $handler) {
            if (str_starts_with($key, $prefix)) {
                $mutationName = substr($key, strlen($prefix));
                $handlers[$mutationName] = $handler;
            }
        }

        return $handlers;
    }

    /**
     * Unregister all handlers for a package (used during package uninstall)
     * 
     * @param string $packageId Package identifier
     * 
     * @return int Number of handlers removed
     */
    public function unregisterPackage(string $packageId): int
    {
        $prefix = $packageId . ':';
        $removed = 0;

        foreach (array_keys($this->queryHandlers) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->queryHandlers[$key]);
                $removed++;
            }
        }

        foreach (array_keys($this->mutationHandlers) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->mutationHandlers[$key]);
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Validate class name against security rules
     * 
     * @param string $className Fully-qualified class name
     * 
     * @throws \RuntimeException if class is from blocked namespace
     * @throws \InvalidArgumentException if class doesn't exist
     */
    private function validateClass(string $className): void
    {
        // Check blocked namespaces
        foreach (self::BLOCKED_NAMESPACES as $blocked) {
            if (str_contains($className, $blocked)) {
                throw new \RuntimeException(
                    "Handler class {$className} is from blocked namespace: {$blocked}"
                );
            }
        }

        // Verify class exists
        if (!class_exists($className)) {
            throw new \InvalidArgumentException(
                "Handler class {$className} does not exist"
            );
        }
    }

    /**
     * Create registry key from package ID and handler name
     * 
     * @param string $packageId Package identifier
     * @param string $handlerName Query or mutation name
     * 
     * @return string Registry key (format: "packageId:handlerName")
     */
    private function makeKey(string $packageId, string $handlerName): string
    {
        return $packageId . ':' . $handlerName;
    }

    /**
     * Get statistics about registered handlers
     * 
     * @return array Handler statistics
     */
    public function getStats(): array
    {
        return [
            'queryHandlers' => count($this->queryHandlers),
            'mutationHandlers' => count($this->mutationHandlers),
            'totalHandlers' => count($this->queryHandlers) + count($this->mutationHandlers),
        ];
    }
}
