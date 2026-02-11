<?php

namespace Hub\Package;

use Opis\JsonSchema\Validator;
use Opis\JsonSchema\Errors\ErrorFormatter;

/**
 * Package Validator
 * 
 * Validates package JSON against Layer 3 schema.
 * Ensures package structure compliance before installation.
 * 
 * @see PACKAGE_ARCHITECTURE_SPEC.md §11 (Package Runtime Contract)
 * @see config/package-schema.json (JSON schema definition)
 */
class PackageValidator
{
    /** @var string Path to package JSON schema */
    private const SCHEMA_PATH = __DIR__ . '/../../config/package-schema.json';

    /** @var Validator JSON schema validator */
    private Validator $validator;

    /** @var object|null Cached schema */
    private ?object $schema = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->validator = new Validator();
    }

    /**
     * Validate package JSON against schema
     * 
     * @param array $packageJson Package definition array
     * 
     * @return array Validation result:
     *   [
     *     'valid' => bool,
     *     'errors' => array,  // Empty if valid, detailed errors if invalid
     *     'warnings' => array // Optional non-critical issues
     *   ]
     */
    public function validate(array $packageJson): array
    {
        $result = [
            'valid' => false,
            'errors' => [],
            'warnings' => []
        ];

        // Load schema
        try {
            $schema = $this->getSchema();
        } catch (\Exception $e) {
            $result['errors'][] = [
                'field' => '_schema',
                'message' => 'Failed to load package schema: ' . $e->getMessage()
            ];
            return $result;
        }

        // Convert array to object for validation
        $data = json_decode(json_encode($packageJson));

        // Validate against schema
        $validationResult = $this->validator->validate($data, $schema);

        if ($validationResult->isValid()) {
            $result['valid'] = true;
            
            // Additional validation checks
            $warnings = $this->performAdditionalValidation($packageJson);
            if (!empty($warnings)) {
                $result['warnings'] = $warnings;
            }
        } else {
            // Format validation errors
            $formatter = new ErrorFormatter();
            $result['errors'] = $this->formatErrors($validationResult->error(), $formatter);
        }

        return $result;
    }

    /**
     * Validate package ID format
     * 
     * @param string $packageId Package ID to validate
     * 
     * @return bool True if valid reverse domain notation
     */
    public function validatePackageId(string $packageId): bool
    {
        return (bool) preg_match('/^[a-z0-9]+\.[a-z0-9\-]+\.[a-z0-9\-]+$/', $packageId);
    }

    /**
     * Validate semantic version format
     * 
     * @param string $version Version string to validate
     * 
     * @return bool True if valid semantic version
     */
    public function validateVersion(string $version): bool
    {
        return (bool) preg_match('/^\d+\.\d+\.\d+$/', $version);
    }

    /**
     * Check if package has required Layer 3 structure
     * 
     * @param array $packageJson Package definition array
     * 
     * @return bool True if package has Layer 3 structure (pages, queries, mutations)
     */
    public function isLayer3Package(array $packageJson): bool
    {
        return isset($packageJson['pages']) 
            && is_array($packageJson['pages']) 
            && count($packageJson['pages']) > 0;
    }

    /**
     * Get all handler classes referenced in package
     * 
     * Used by HandlerRegistry to register all handlers during package installation.
     * 
     * @param array $packageJson Package definition array
     * 
     * @return array [
     *   'queries' => ['queryName' => 'HandlerClass', ...],
     *   'mutations' => ['mutationName' => 'HandlerClass', ...]
     * ]
     */
    public function extractHandlers(array $packageJson): array
    {
        $handlers = [
            'queries' => [],
            'mutations' => []
        ];

        // Extract query handlers
        if (isset($packageJson['queries']) && is_array($packageJson['queries'])) {
            foreach ($packageJson['queries'] as $queryName => $queryDef) {
                if (isset($queryDef['handler'])) {
                    $handlers['queries'][$queryName] = $queryDef['handler'];
                }
            }
        }

        // Extract mutation handlers
        if (isset($packageJson['mutations']) && is_array($packageJson['mutations'])) {
            foreach ($packageJson['mutations'] as $mutationName => $mutationDef) {
                if (isset($mutationDef['handler'])) {
                    $handlers['mutations'][$mutationName] = $mutationDef['handler'];
                }
            }
        }

        return $handlers;
    }

    /**
     * Extract all data fields with their classifications
     * 
     * Used by ProjectionEngine to apply field masking.
     * 
     * @param array $packageJson Package definition array
     * 
     * @return array Field key => data classification map
     */
    public function extractDataClassifications(array $packageJson): array
    {
        $classifications = [];

        if (!isset($packageJson['pages']) || !is_array($packageJson['pages'])) {
            return $classifications;
        }

        foreach ($packageJson['pages'] as $page) {
            if (!isset($page['components']) || !is_array($page['components'])) {
                continue;
            }

            foreach ($page['components'] as $component) {
                if (!isset($component['columns']) || !is_array($component['columns'])) {
                    continue;
                }

                foreach ($component['columns'] as $column) {
                    if (isset($column['key']) && isset($column['dataClass'])) {
                        $classifications[$column['key']] = $column['dataClass'];
                    }
                }
            }
        }

        return $classifications;
    }

    /**
     * Get package schema
     * 
     * @return object JSON schema object
     * 
     * @throws \RuntimeException if schema file not found or invalid
     */
    private function getSchema(): object
    {
        if ($this->schema === null) {
            if (!file_exists(self::SCHEMA_PATH)) {
                throw new \RuntimeException('Package schema file not found: ' . self::SCHEMA_PATH);
            }

            $schemaContent = file_get_contents(self::SCHEMA_PATH);
            $this->schema = json_decode($schemaContent);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid package schema JSON: ' . json_last_error_msg());
            }
        }

        return $this->schema;
    }

    /**
     * Perform additional validation beyond schema
     * 
     * Checks for logical consistency, best practices, potential issues.
     * 
     * @param array $packageJson Package definition array
     * 
     * @return array Warning messages
     */
    private function performAdditionalValidation(array $packageJson): array
    {
        $warnings = [];

        // Check for pages without queries or mutations
        if (isset($packageJson['pages'])) {
            foreach ($packageJson['pages'] as $index => $page) {
                $hasQueries = false;
                $hasMutations = false;

                if (isset($page['components'])) {
                    foreach ($page['components'] as $component) {
                        if (isset($component['query'])) $hasQueries = true;
                        if (isset($component['mutation'])) $hasMutations = true;
                    }
                }

                if (!$hasQueries && !$hasMutations) {
                    $warnings[] = [
                        'field' => "pages[{$index}]",
                        'message' => "Page '{$page['id']}' has no queries or mutations (static page)"
                    ];
                }
            }
        }

        // Check for referenced queries/mutations that don't exist
        $definedQueries = array_keys($packageJson['queries'] ?? []);
        $definedMutations = array_keys($packageJson['mutations'] ?? []);

        if (isset($packageJson['pages'])) {
            foreach ($packageJson['pages'] as $pageIndex => $page) {
                if (isset($page['components'])) {
                    foreach ($page['components'] as $compIndex => $component) {
                        // Check query references
                        if (isset($component['query'])) {
                            $queryName = $component['query'];
                            if (!in_array($queryName, $definedQueries)) {
                                $warnings[] = [
                                    'field' => "pages[{$pageIndex}].components[{$compIndex}].query",
                                    'message' => "Query '{$queryName}' is not defined in queries{}"
                                ];
                            }
                        }

                        // Check mutation references
                        if (isset($component['mutation'])) {
                            $mutationName = $component['mutation'];
                            if (!in_array($mutationName, $definedMutations)) {
                                $warnings[] = [
                                    'field' => "pages[{$pageIndex}].components[{$compIndex}].mutation",
                                    'message' => "Mutation '{$mutationName}' is not defined in mutations{}"
                                ];
                            }
                        }

                        // Check action mutations
                        if (isset($component['actions'])) {
                            foreach ($component['actions'] as $actionIndex => $action) {
                                if (isset($action['mutation'])) {
                                    $mutationName = $action['mutation'];
                                    if (!in_array($mutationName, $definedMutations)) {
                                        $warnings[] = [
                                            'field' => "pages[{$pageIndex}].components[{$compIndex}].actions[{$actionIndex}].mutation",
                                            'message' => "Mutation '{$mutationName}' is not defined in mutations{}"
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Check for password fields without proper classification
        if (isset($packageJson['pages'])) {
            foreach ($packageJson['pages'] as $pageIndex => $page) {
                if (isset($page['components'])) {
                    foreach ($page['components'] as $compIndex => $component) {
                        if (isset($component['columns'])) {
                            foreach ($component['columns'] as $colIndex => $column) {
                                if (isset($column['type']) && $column['type'] === 'password') {
                                    if (!isset($column['dataClass']) || $column['dataClass'] !== 'secret') {
                                        $warnings[] = [
                                            'field' => "pages[{$pageIndex}].components[{$compIndex}].columns[{$colIndex}]",
                                            'message' => "Password field '{$column['key']}' should have dataClass: 'secret'"
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $warnings;
    }

    /**
     * Format validation errors into readable array
     * 
     * @param mixed $error Error object from Opis validator
     * @param ErrorFormatter $formatter Error formatter
     * 
     * @return array Formatted error messages
     */
    private function formatErrors($error, ErrorFormatter $formatter): array
    {
        $formatted = $formatter->format($error);
        $errors = [];

        if (is_array($formatted)) {
            foreach ($formatted as $field => $messages) {
                if (is_array($messages)) {
                    foreach ($messages as $message) {
                        $errors[] = [
                            'field' => $field,
                            'message' => $message
                        ];
                    }
                } else {
                    $errors[] = [
                        'field' => $field,
                        'message' => $messages
                    ];
                }
            }
        } else {
            $errors[] = [
                'field' => '_root',
                'message' => (string) $formatted
            ];
        }

        return $errors;
    }
}
