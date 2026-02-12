<?php

namespace Hub\Package\StudentDirectory;

/**
 * Student Directory Package Handler
 *
 * Unified handler bridging the PageRouter query/mutation dispatch
 * to the individual StudentQueryHandler, StudentMutationHandler,
 * ExportHandler, and ImportHandler classes.
 *
 * Registered in public/p/index.php for the 'district.student-directory' package.
 */
class StudentDirectoryHandler
{
    /** Query name → handler method mapping */
    private const QUERY_MAP = [
        'listStudents'     => [StudentQueryHandler::class, 'listStudents'],
        'getStudent'       => [StudentQueryHandler::class, 'getStudent'],
        'getStats'         => [StudentQueryHandler::class, 'getStats'],
        'getGrades'        => [StudentQueryHandler::class, 'getGrades'],
        'getGraduationYears' => [StudentQueryHandler::class, 'getGraduationYears'],
        'getImportHistory' => [StudentQueryHandler::class, 'getImportHistory'],
        'searchStudents'   => [StudentQueryHandler::class, 'searchStudents'],
    ];

    /** Mutation name → handler method mapping */
    private const MUTATION_MAP = [
        'createStudent'  => [StudentMutationHandler::class, 'createStudent'],
        'updateStudent'  => [StudentMutationHandler::class, 'updateStudent'],
        'resetPassword'  => [StudentMutationHandler::class, 'resetPassword'],
        'bulkDelete'     => [StudentMutationHandler::class, 'bulkDelete'],
        'printCards'     => [StudentMutationHandler::class, 'printCards'],
    ];

    public function getPackageId(): string
    {
        return 'district.student-directory';
    }

    public function getSupportedQueries(): array
    {
        return array_keys(self::QUERY_MAP);
    }

    public function getSupportedMutations(): array
    {
        return array_keys(self::MUTATION_MAP);
    }

    /**
     * Execute a query through the appropriate handler
     *
     * @param string $queryName  Query identifier from package JSON
     * @param array  $params     Request/route params
     * @param array  $context    ['user', 'packageId', 'queryName', 'pageConfig']
     * @return array Data result for component rendering
     */
    public function executeQuery(string $queryName, array $params, array $context): array
    {
        if (!isset(self::QUERY_MAP[$queryName])) {
            return ['data' => [], 'error' => "Unknown query: {$queryName}"];
        }

        [$class, $method] = self::QUERY_MAP[$queryName];

        try {
            // Extract and pass parameters based on method signatures
            $result = match ($queryName) {
                'listStudents'       => $class::$method($params),
                'getStudent'         => $class::$method((string)($params['id'] ?? $params['student_id'] ?? '')),
                'getStats'           => $class::$method(),
                'getGrades'          => $class::$method(),
                'getGraduationYears' => $class::$method(),
                'getImportHistory'   => $class::$method((int)($params['limit'] ?? 20)),
                'searchStudents'     => $class::$method((string)($params['query'] ?? $params['search'] ?? ''), (int)($params['limit'] ?? 10)),
                default              => $class::$method($params),
            };

            // Normalize result shape for component renderers
            // Table renderer expects: { data: [], meta: { total, page, perPage } }
            // Dashboard renderer expects: key-value pairs
            // Detail renderer expects: single record as data

            if ($queryName === 'listStudents') {
                // Already returns { data: [], pagination: { total, page, per_page, ... } }
                return [
                    'data' => $result['data'] ?? [],
                    'meta' => [
                        'total' => $result['pagination']['total'] ?? 0,
                        'page' => $result['pagination']['page'] ?? 1,
                        'perPage' => $result['pagination']['per_page'] ?? 50,
                        'totalPages' => $result['pagination']['total_pages'] ?? 1,
                    ],
                ];
            }

            if ($queryName === 'getStudent') {
                // Returns single record or null
                return ['data' => $result ?? []];
            }

            if ($queryName === 'getStats') {
                // Returns stats object — flatten for dashboard cards
                return $result;
            }

            if (in_array($queryName, ['getGrades', 'getGraduationYears'])) {
                // Returns arrays of values
                return ['data' => $result];
            }

            if ($queryName === 'getImportHistory') {
                // Returns array of history records
                return [
                    'data' => $result,
                    'meta' => ['total' => count($result), 'page' => 1, 'perPage' => 10],
                ];
            }

            // Default passthrough
            return is_array($result) ? $result : ['data' => $result];
        } catch (\Exception $e) {
            return ['data' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Execute a mutation through the appropriate handler
     *
     * @param string $mutationName  Mutation identifier
     * @param array  $input         Form/request data
     * @param array  $context       ['user', 'packageId']
     * @return array Result with success/error status
     */
    public function executeMutation(string $mutationName, array $input, array $context): array
    {
        if (!isset(self::MUTATION_MAP[$mutationName])) {
            return ['success' => false, 'error' => "Unknown mutation: {$mutationName}"];
        }

        [$class, $method] = self::MUTATION_MAP[$mutationName];

        try {
            return $class::$method($input);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
