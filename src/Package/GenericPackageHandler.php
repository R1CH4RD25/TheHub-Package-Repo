<?php

namespace Hub\Package;

use Hub\Database;
use Hub\AuditLogger;

/**
 * Generic Package Handler
 *
 * The "poof create" engine: executes queries and mutations for ANY package
 * based purely on its JSON definition — no custom PHP handler classes needed.
 *
 * Reads the package's `database` config (connection, primaryTable) and
 * `data.queries` / `data.mutations` definitions to auto-generate SQL.
 *
 * Query types (inferred from handler string or explicit `type` field):
 * - list:    SELECT with search, filter, pagination, sort
 * - get:     SELECT WHERE id = ?
 * - stats:   COUNT/SUM aggregation for dashboard KPIs
 * - options: Simple SELECT for dropdown/select fields
 * - count:   COUNT(*) grouped query
 *
 * Mutation types:
 * - create:  INSERT from form fields
 * - update:  UPDATE WHERE id = ?
 * - delete:  Soft-delete (is_active = 0) or hard DELETE
 * - toggle:  Toggle a boolean/status field
 * - status:  Update status field (approve/reject/etc.)
 *
 * @see CONTRIBUTING.md for package JSON schema documentation
 * @see PACKAGE_ARCHITECTURE_SPEC.md §14
 */
class GenericPackageHandler
{
    private array $packageData;
    private string $packageId;
    private ?Database $db = null;
    private ?string $dbName = null;

    /** @var array Cached table column metadata */
    private array $tableColumns = [];

    /** @var string[] Allowed sort directions */
    private const SORT_DIRS = ['ASC', 'DESC'];

    /** @var int Maximum page size to prevent abuse */
    private const MAX_PAGE_SIZE = 500;

    /** @var int Default page size */
    private const DEFAULT_PAGE_SIZE = 50;

    public function __construct(array $packageData, string $packageId)
    {
        $this->packageData = $packageData;
        $this->packageId = $packageId;
        $this->dbName = $packageData['database']['connection'] ?? null;
    }

    /**
     * Execute a query by name
     *
     * @param string $queryName Query identifier from package JSON
     * @param array  $params    Request parameters (search, filters, page, sort, etc.)
     * @param array  $context   ['user', 'packageId', 'queryName', 'pageConfig']
     * @return array Data result for component rendering
     */
    public function executeQuery(string $queryName, array $params, array $context): array
    {
        $queries = $this->packageData['data']['queries'] ?? $this->packageData['queries'] ?? [];
        $queryDef = $queries[$queryName] ?? null;

        if (!$queryDef) {
            return ['data' => [], 'error' => "Query '{$queryName}' not defined in package"];
        }

        // Determine query type from explicit `type` field or handler string
        $type = $this->inferQueryType($queryDef);

        try {
            $db = $this->getDatabase();
            if (!$db) {
                return $this->emptyResult($params, "Database '{$this->dbName}' not available");
            }

            return match ($type) {
                'list'    => $this->executeListQuery($db, $queryDef, $queryName, $params),
                'get'     => $this->executeGetQuery($db, $queryDef, $queryName, $params),
                'stats'   => $this->executeStatsQuery($db, $queryDef, $queryName, $params),
                'options' => $this->executeOptionsQuery($db, $queryDef, $queryName, $params),
                'count'   => $this->executeCountQuery($db, $queryDef, $queryName, $params),
                default   => $this->executeListQuery($db, $queryDef, $queryName, $params),
            };
        } catch (\PDOException $e) {
            error_log("[GenericPackageHandler] Query error for {$this->packageId}::{$queryName}: " . $e->getMessage());

            // Table doesn't exist yet — return empty gracefully
            if (str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), 'Table') || $e->getCode() === '42S02') {
                return $this->emptyResult($params, "Table not yet created. Run the package database migration.");
            }

            return ['data' => [], 'error' => 'Database query failed: ' . $e->getMessage()];
        } catch (\Exception $e) {
            error_log("[GenericPackageHandler] Error for {$this->packageId}::{$queryName}: " . $e->getMessage());
            return ['data' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Execute a mutation by name
     *
     * @param string $mutationName Mutation identifier
     * @param array  $input        Form/request data
     * @param array  $context      ['user', 'packageId']
     * @return array Result with success/error/data
     */
    public function executeMutation(string $mutationName, array $input, array $context): array
    {
        $mutations = $this->packageData['data']['mutations'] ?? $this->packageData['mutations'] ?? [];
        $mutationDef = $mutations[$mutationName] ?? null;

        if (!$mutationDef) {
            return ['success' => false, 'error' => "Mutation '{$mutationName}' not defined in package"];
        }

        $type = $this->inferMutationType($mutationDef, $mutationName);

        try {
            $db = $this->getDatabase();
            if (!$db) {
                return ['success' => false, 'error' => "Database '{$this->dbName}' not available"];
            }

            $result = match ($type) {
                'create' => $this->executeCreateMutation($db, $mutationDef, $mutationName, $input, $context),
                'update' => $this->executeUpdateMutation($db, $mutationDef, $mutationName, $input, $context),
                'delete' => $this->executeDeleteMutation($db, $mutationDef, $mutationName, $input, $context),
                'toggle' => $this->executeToggleMutation($db, $mutationDef, $mutationName, $input, $context),
                'status' => $this->executeStatusMutation($db, $mutationDef, $mutationName, $input, $context),
                default  => ['success' => false, 'error' => "Unknown mutation type: {$type}"],
            };

            // Audit log if requested
            if (!empty($mutationDef['audit']) && ($result['success'] ?? false)) {
                $this->auditLog($mutationName, $input, $context, $result);
            }

            return $result;
        } catch (\PDOException $e) {
            error_log("[GenericPackageHandler] Mutation error for {$this->packageId}::{$mutationName}: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        } catch (\Exception $e) {
            error_log("[GenericPackageHandler] Mutation error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ──────────────────────────────────────────────────
    // QUERY IMPLEMENTATIONS
    // ──────────────────────────────────────────────────

    /**
     * Execute a list query with search, filter, pagination, sort
     */
    private function executeListQuery(Database $db, array $queryDef, string $queryName, array $params): array
    {
        $table = $this->resolveTable($queryDef);
        $columns = $this->resolveSelectColumns($queryDef, $queryName);
        $joins = $this->resolveJoins($queryDef);
        $softDelete = $this->resolveSoftDelete($queryDef);

        $where = [];
        $bindings = [];

        // Soft delete filter
        if ($softDelete) {
            $where[] = $softDelete;
        }

        // Search
        if (!empty($params['search'])) {
            $searchCols = $this->resolveSearchColumns($queryDef, $queryName, $table);
            if (!empty($searchCols)) {
                $searchClauses = [];
                $searchTerm = '%' . $params['search'] . '%';
                foreach ($searchCols as $col) {
                    $searchClauses[] = "{$col} LIKE ?";
                    $bindings[] = $searchTerm;
                }
                $where[] = '(' . implode(' OR ', $searchClauses) . ')';
            }
        }

        // Filters from query parameters
        $filterDefs = $queryDef['filterColumns'] ?? $this->inferFilterColumns($queryDef, $queryName);
        foreach ($filterDefs as $paramKey => $dbColumn) {
            if (!empty($params[$paramKey]) && $params[$paramKey] !== '' && $params[$paramKey] !== 'all') {
                $where[] = "{$dbColumn} = ?";
                $bindings[] = $params[$paramKey];
            }
        }

        $whereClause = empty($where) ? '1=1' : implode(' AND ', $where);

        // Count total
        $countSql = "SELECT COUNT(*) as total FROM {$table} {$joins} WHERE {$whereClause}";
        $countRow = $db->fetchOne($countSql, $bindings);
        $total = (int)($countRow['total'] ?? 0);

        // Sort
        $sortColumn = $this->resolveSortColumn($queryDef, $params, $table);
        $sortDir = strtoupper($params['sortDir'] ?? $params['sort_dir'] ?? 'ASC');
        if (!in_array($sortDir, self::SORT_DIRS)) {
            $sortDir = 'ASC';
        }

        // Pagination
        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = min(self::MAX_PAGE_SIZE, max(1, (int)($params['pageSize'] ?? $params['per_page'] ?? self::DEFAULT_PAGE_SIZE)));
        $offset = ($page - 1) * $perPage;

        // Build SELECT
        $selectCols = $columns ?: '*';
        $sql = "SELECT {$selectCols} FROM {$table} {$joins} WHERE {$whereClause} ORDER BY {$sortColumn} {$sortDir} LIMIT {$perPage} OFFSET {$offset}";

        $rows = $db->fetchAll($sql, $bindings);

        return [
            'data' => $rows,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => (int)ceil($total / max(1, $perPage)),
            ],
        ];
    }

    /**
     * Execute a get-by-ID query
     */
    private function executeGetQuery(Database $db, array $queryDef, string $queryName, array $params): array
    {
        $table = $this->resolveTable($queryDef);
        $columns = $this->resolveSelectColumns($queryDef, $queryName);
        $joins = $this->resolveJoins($queryDef);
        $idColumn = $queryDef['idColumn'] ?? 'id';

        $id = $params['id'] ?? $params[$idColumn] ?? null;
        if (!$id) {
            return ['data' => null, 'error' => 'ID parameter required'];
        }

        $selectCols = $columns ?: '*';
        $sql = "SELECT {$selectCols} FROM {$table} {$joins} WHERE {$table}.{$idColumn} = ?";
        $row = $db->fetchOne($sql, [$id]);

        return ['data' => $row ?: null];
    }

    /**
     * Execute a stats/aggregation query for dashboards
     */
    private function executeStatsQuery(Database $db, array $queryDef, string $queryName, array $params): array
    {
        $table = $this->resolveTable($queryDef);

        // If explicit SQL is provided (advanced), use it
        if (!empty($queryDef['sql'])) {
            $row = $db->fetchOne($queryDef['sql']);
            return ['data' => $row ?: []];
        }

        // If aggregations are defined in the query, build them
        if (!empty($queryDef['aggregations'])) {
            return $this->executeDefinedAggregations($db, $queryDef, $table);
        }

        // Auto-generate basic stats: total count + status counts
        $stats = [];

        // Total count
        $softDelete = $this->resolveSoftDelete($queryDef);
        $sdClause = $softDelete ? "WHERE {$softDelete}" : '';
        $countRow = $db->fetchOne("SELECT COUNT(*) as total FROM {$table} {$sdClause}");
        $stats['total'] = (int)($countRow['total'] ?? 0);

        // Try to get status distribution if status column exists
        if ($this->tableHasColumn($db, $table, 'status')) {
            $rows = $db->fetchAll("SELECT status, COUNT(*) as count FROM {$table} {$sdClause} GROUP BY status");
            foreach ($rows as $row) {
                $key = ($row['status'] ?? 'unknown') . '_count';
                $stats[$key] = (int)$row['count'];
            }
        }

        return ['data' => $stats];
    }

    /**
     * Execute aggregations defined in the query
     */
    private function executeDefinedAggregations(Database $db, array $queryDef, string $table): array
    {
        $aggregations = $queryDef['aggregations'];
        $selects = [];
        foreach ($aggregations as $alias => $expr) {
            // Sanitize: only allow safe SQL aggregate expressions
            if (preg_match('/^(COUNT|SUM|AVG|MIN|MAX)\s*\(.+\)$/i', $expr)) {
                $selects[] = "{$expr} AS {$alias}";
            }
        }

        if (empty($selects)) {
            return ['data' => []];
        }

        $softDelete = $this->resolveSoftDelete($queryDef);
        $sdClause = $softDelete ? "WHERE {$softDelete}" : '';
        $sql = "SELECT " . implode(', ', $selects) . " FROM {$table} {$sdClause}";
        $row = $db->fetchOne($sql);

        return ['data' => $row ?: []];
    }

    /**
     * Execute an options query (simple list for dropdowns)
     */
    private function executeOptionsQuery(Database $db, array $queryDef, string $queryName, array $params): array
    {
        $table = $this->resolveTable($queryDef);

        $softDelete = $this->resolveSoftDelete($queryDef);
        $sdClause = $softDelete ? "WHERE {$softDelete}" : '';

        // Use explicit select clause if provided
        if (!empty($queryDef['select'])) {
            $orderBy = $queryDef['orderBy'] ?? '1';
            $sql = "SELECT {$queryDef['select']} FROM {$table} {$sdClause} ORDER BY {$orderBy}";
            $rows = $db->fetchAll($sql);
            return ['data' => $rows];
        }

        $valueCol = $queryDef['valueColumn'] ?? 'id';
        $labelCol = $queryDef['labelColumn'] ?? null;

        // If no labelColumn specified, try to auto-detect from table schema
        if (!$labelCol) {
            $candidates = ['name', 'display_name', 'label', 'title', 'description'];
            foreach ($candidates as $candidate) {
                if ($this->tableHasColumn($db, $table, $candidate)) {
                    $labelCol = $candidate;
                    break;
                }
            }
        }

        if ($labelCol) {
            $orderBy = $queryDef['orderBy'] ?? $labelCol;
            $sql = "SELECT {$valueCol} as value, {$labelCol} as label FROM {$table} {$sdClause} ORDER BY {$orderBy}";
        } else {
            // Fallback: just select all columns
            $sql = "SELECT * FROM {$table} {$sdClause} ORDER BY {$valueCol}";
        }

        $rows = $db->fetchAll($sql);

        return ['data' => $rows];
    }

    /**
     * Execute a count query
     */
    private function executeCountQuery(Database $db, array $queryDef, string $queryName, array $params): array
    {
        $table = $this->resolveTable($queryDef);
        $groupBy = $queryDef['groupBy'] ?? null;

        $softDelete = $this->resolveSoftDelete($queryDef);
        $sdClause = $softDelete ? "WHERE {$softDelete}" : '';

        if ($groupBy) {
            $sql = "SELECT {$groupBy}, COUNT(*) as count FROM {$table} {$sdClause} GROUP BY {$groupBy}";
            $rows = $db->fetchAll($sql);
            return ['data' => $rows];
        }

        $countRow = $db->fetchOne("SELECT COUNT(*) as total FROM {$table} {$sdClause}");
        return ['data' => ['total' => (int)($countRow['total'] ?? 0)]];
    }

    // ──────────────────────────────────────────────────
    // MUTATION IMPLEMENTATIONS
    // ──────────────────────────────────────────────────

    /**
     * Execute a CREATE mutation (INSERT)
     */
    private function executeCreateMutation(Database $db, array $mutationDef, string $mutationName, array $input, array $context): array
    {
        $table = $this->resolveTable($mutationDef);
        $allowedFields = $this->resolveMutationFields($mutationDef, $mutationName);

        // Filter input to only allowed fields
        $data = [];
        foreach ($input as $key => $value) {
            if (empty($allowedFields) || in_array($key, $allowedFields)) {
                // Skip meta fields
                if (in_array($key, ['csrfToken', 'csrf_token', '_method', '_token', 'packageId', 'mutationName'])) {
                    continue;
                }
                $data[$this->sanitizeColumnName($key)] = $value;
            }
        }

        if (empty($data)) {
            return ['success' => false, 'error' => 'No valid fields provided'];
        }

        // Add audit columns if they exist
        if ($this->tableHasColumn($db, $table, 'created_at')) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        if ($this->tableHasColumn($db, $table, 'created_by') && isset($context['user']['id'])) {
            $data['created_by'] = $context['user']['id'];
        }

        $id = $db->insert($table, $data);

        return [
            'success' => true,
            'data' => ['id' => $id],
            'message' => 'Record created successfully',
        ];
    }

    /**
     * Execute an UPDATE mutation
     */
    private function executeUpdateMutation(Database $db, array $mutationDef, string $mutationName, array $input, array $context): array
    {
        $table = $this->resolveTable($mutationDef);
        $idColumn = $mutationDef['idColumn'] ?? 'id';
        $id = $input['id'] ?? $input[$idColumn] ?? null;

        if (!$id) {
            return ['success' => false, 'error' => 'Record ID required'];
        }

        $allowedFields = $this->resolveMutationFields($mutationDef, $mutationName);

        $data = [];
        foreach ($input as $key => $value) {
            if ($key === 'id' || $key === $idColumn) continue;
            if (in_array($key, ['csrfToken', 'csrf_token', '_method', '_token', 'packageId', 'mutationName'])) continue;
            if (empty($allowedFields) || in_array($key, $allowedFields)) {
                $data[$this->sanitizeColumnName($key)] = $value;
            }
        }

        if (empty($data)) {
            return ['success' => false, 'error' => 'No valid fields to update'];
        }

        // Add audit columns
        if ($this->tableHasColumn($db, $table, 'updated_at')) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        if ($this->tableHasColumn($db, $table, 'updated_by') && isset($context['user']['id'])) {
            $data['updated_by'] = $context['user']['id'];
        }

        $db->update($table, (int)$id, $data);

        return [
            'success' => true,
            'data' => ['id' => $id],
            'message' => 'Record updated successfully',
        ];
    }

    /**
     * Execute a DELETE mutation (soft-delete by default)
     */
    private function executeDeleteMutation(Database $db, array $mutationDef, string $mutationName, array $input, array $context): array
    {
        $table = $this->resolveTable($mutationDef);
        $idColumn = $mutationDef['idColumn'] ?? 'id';
        $id = $input['id'] ?? $input[$idColumn] ?? null;

        if (!$id) {
            return ['success' => false, 'error' => 'Record ID required'];
        }

        // Soft delete if table has is_active column
        if ($this->tableHasColumn($db, $table, 'is_active')) {
            $data = ['is_active' => 0];
            if ($this->tableHasColumn($db, $table, 'updated_at')) {
                $data['updated_at'] = date('Y-m-d H:i:s');
            }
            $db->update($table, (int)$id, $data);
        } else {
            // Hard delete only if explicitly allowed
            if (!empty($mutationDef['hardDelete'])) {
                $db->execute("DELETE FROM {$table} WHERE {$idColumn} = ?", [$id]);
            } else {
                return ['success' => false, 'error' => 'Soft delete not available (no is_active column) and hard delete not permitted'];
            }
        }

        return [
            'success' => true,
            'data' => ['id' => $id],
            'message' => 'Record deleted successfully',
        ];
    }

    /**
     * Execute a toggle mutation (flip a boolean/status field)
     */
    private function executeToggleMutation(Database $db, array $mutationDef, string $mutationName, array $input, array $context): array
    {
        $table = $this->resolveTable($mutationDef);
        $idColumn = $mutationDef['idColumn'] ?? 'id';
        $id = $input['id'] ?? $input[$idColumn] ?? null;
        $toggleField = $mutationDef['toggleField'] ?? $this->inferToggleField($mutationName);

        if (!$id) {
            return ['success' => false, 'error' => 'Record ID required'];
        }
        if (!$toggleField) {
            return ['success' => false, 'error' => 'Toggle field not specified'];
        }

        // Get current value
        $row = $db->fetchOne("SELECT {$toggleField} FROM {$table} WHERE {$idColumn} = ?", [$id]);
        if (!$row) {
            return ['success' => false, 'error' => 'Record not found'];
        }

        // Toggle the value
        $currentValue = $row[$toggleField];
        $newValue = $this->toggleValue($currentValue);

        $data = [$toggleField => $newValue];
        if ($this->tableHasColumn($db, $table, 'updated_at')) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $db->update($table, (int)$id, $data);

        return [
            'success' => true,
            'data' => ['id' => $id, $toggleField => $newValue],
            'message' => ucfirst($toggleField) . ' toggled successfully',
        ];
    }

    /**
     * Execute a status change mutation (approve, reject, etc.)
     */
    private function executeStatusMutation(Database $db, array $mutationDef, string $mutationName, array $input, array $context): array
    {
        $table = $this->resolveTable($mutationDef);
        $idColumn = $mutationDef['idColumn'] ?? 'id';
        $id = $input['id'] ?? $input[$idColumn] ?? null;
        $statusField = $mutationDef['statusField'] ?? 'status';
        $newStatus = $mutationDef['newStatus'] ?? $input['status'] ?? $this->inferStatus($mutationName);

        if (!$id) {
            return ['success' => false, 'error' => 'Record ID required'];
        }
        if (!$newStatus) {
            return ['success' => false, 'error' => 'Target status not specified'];
        }

        $data = [$statusField => $newStatus];
        if ($this->tableHasColumn($db, $table, 'updated_at')) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        if ($this->tableHasColumn($db, $table, 'updated_by') && isset($context['user']['id'])) {
            $data['updated_by'] = $context['user']['id'];
        }

        // Add specific timestamp columns (e.g., approved_at, rejected_at)
        $timestampCol = $newStatus . '_at';
        if ($this->tableHasColumn($db, $table, $timestampCol)) {
            $data[$timestampCol] = date('Y-m-d H:i:s');
        }
        $byCol = $newStatus . '_by';
        if ($this->tableHasColumn($db, $table, $byCol) && isset($context['user']['id'])) {
            $data[$byCol] = $context['user']['id'];
        }

        $db->update($table, (int)$id, $data);

        return [
            'success' => true,
            'data' => ['id' => $id, 'status' => $newStatus],
            'message' => 'Status updated to ' . $newStatus,
        ];
    }

    // ──────────────────────────────────────────────────
    // RESOLUTION HELPERS (read package JSON to build SQL)
    // ──────────────────────────────────────────────────

    /**
     * Get or create database connection
     *
     * Uses the package's `database.connection` to determine the database.
     * If it's the same as the hub DB, reuse the singleton.
     * For external databases, create a connection to the same host with the specified DB name.
     */
    private function getDatabase(): ?Database
    {
        if ($this->db) {
            return $this->db;
        }

        $hubDb = Database::getInstance();

        // If no custom database specified, use the hub database
        if (!$this->dbName) {
            $this->db = $hubDb;
            return $this->db;
        }

        // Check if it's the same database
        $hubDbName = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'woodson_hub';
        if ($this->dbName === $hubDbName) {
            $this->db = $hubDb;
            return $this->db;
        }

        // For external databases, we use cross-database queries via the hub connection
        // MySQL allows `database.table` syntax when the user has access to both databases
        // This avoids creating separate PDO connections
        $this->db = $hubDb;
        return $this->db;
    }

    /**
     * Resolve table name for a query/mutation
     *
     * Priority: queryDef.table → database.primaryTable
     * Prefixes with database name for cross-database queries.
     */
    private function resolveTable(array $def): string
    {
        $table = $def['table'] ?? $this->packageData['database']['primaryTable'] ?? null;

        if (!$table) {
            throw new \RuntimeException("No table specified for query and no primaryTable in database config");
        }

        // Sanitize table name (prevent SQL injection)
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $table)) {
            throw new \RuntimeException("Invalid table name: {$table}");
        }

        // Prefix with database name for cross-database queries
        $hubDbName = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'woodson_hub';
        if ($this->dbName && $this->dbName !== $hubDbName && !str_contains($table, '.')) {
            $table = $this->dbName . '.' . $table;
        }

        return $table;
    }

    /**
     * Resolve SELECT columns for a query
     *
     * Priority:
     * 1. queryDef.columns (explicit mapping: alias → expression)
     * 2. queryDef.select (raw SQL select clause)
     * 3. Fallback to '*' (component columns may not match DB columns)
     *
     * NOTE: We intentionally DON'T auto-infer from component columns
     * because component keys (e.g., 'full_name', 'status_badge') are often
     * computed/virtual and don't match actual DB column names.
     * Use queryDef.columns or queryDef.select for explicit mapping.
     */
    private function resolveSelectColumns(array $queryDef, string $queryName): string
    {
        // Explicit columns in query definition
        if (!empty($queryDef['columns'])) {
            if (is_array($queryDef['columns'])) {
                if (array_is_list($queryDef['columns'])) {
                    // Simple array: ['id', 'name', 'status']
                    return implode(', ', array_map([$this, 'sanitizeColumnName'], $queryDef['columns']));
                } else {
                    // Associative: { 'display_name': 'CONCAT(year, " ", make, " ", model)' }
                    $parts = [];
                    foreach ($queryDef['columns'] as $alias => $expr) {
                        $parts[] = "{$expr} AS {$this->sanitizeColumnName($alias)}";
                    }
                    return implode(', ', $parts);
                }
            }
            return $queryDef['columns']; // String: raw SQL
        }

        // Raw select clause
        if (!empty($queryDef['select'])) {
            return $queryDef['select'];
        }

        // Default: SELECT * — safest when we don't know column mapping
        return '*';
    }

    /**
     * Resolve JOIN clauses
     */
    private function resolveJoins(array $queryDef): string
    {
        if (empty($queryDef['joins'])) {
            return '';
        }

        if (is_array($queryDef['joins'])) {
            return implode(' ', $queryDef['joins']);
        }

        return (string)$queryDef['joins'];
    }

    /**
     * Resolve soft-delete WHERE clause
     *
     * Returns clause like "table.is_active = 1" or null if no soft delete
     */
    private function resolveSoftDelete(array $def): ?string
    {
        if (isset($def['softDelete'])) {
            if ($def['softDelete'] === false) return null;
            if (is_string($def['softDelete'])) return "{$def['softDelete']} = 1";
        }

        // Default: check if table has is_active column
        // For schemas where we know the convention, assume is_active
        return null; // Don't add is_active filter unless explicitly specified — table may not have it
    }

    /**
     * Resolve search columns for LIKE matching
     *
     * Only uses explicitly defined searchColumns from the query definition.
     * Does NOT auto-infer from component columns because those may be
     * computed/virtual (e.g., 'full_name' = CONCAT(first_name, last_name)).
     */
    private function resolveSearchColumns(array $queryDef, string $queryName, string $table): array
    {
        // Explicit search columns only
        if (!empty($queryDef['searchColumns'])) {
            return $queryDef['searchColumns'];
        }

        // No inference — package author must specify searchColumns for search to work
        // This prevents SQL errors from component keys that don't match DB columns
        return [];
    }

    /**
     * Resolve filter column mappings
     *
     * Maps request parameter names to actual DB column names for WHERE clauses.
     */
    private function inferFilterColumns(array $queryDef, string $queryName): array
    {
        $filters = [];
        $queryParams = $queryDef['parameters'] ?? [];

        foreach ($queryParams as $paramName => $paramDef) {
            // Skip standard pagination/search params
            if (in_array($paramName, ['search', 'page', 'pageSize', 'per_page', 'sort', 'sortDir', 'sort_dir'])) {
                continue;
            }
            // The parameter name IS the column name (convention)
            $filters[$paramName] = $paramName;
        }

        return $filters;
    }

    /**
     * Resolve sort column
     */
    private function resolveSortColumn(array $queryDef, array $params, string $table): string
    {
        $requestedSort = $params['sort'] ?? null;

        if ($requestedSort) {
            // Validate the sort column exists in the component columns
            $sanitized = $this->sanitizeColumnName($requestedSort);
            if ($sanitized) {
                return $sanitized;
            }
        }

        // Default sort from query definition
        if (!empty($queryDef['defaultSort'])) {
            return $queryDef['defaultSort'];
        }

        // Default to id or first component column
        return 'id';
    }

    /**
     * Resolve mutation fields (allowed field names for INSERT/UPDATE)
     *
     * Priority:
     * 1. mutationDef.fields (explicit list)
     * 2. Form component fields (inferred from presentation)
     * 3. Empty (allow all non-meta fields)
     */
    private function resolveMutationFields(array $mutationDef, string $mutationName): array
    {
        // Explicit fields in mutation definition
        if (!empty($mutationDef['fields'])) {
            return $mutationDef['fields'];
        }

        // Infer from form component
        $fields = $this->findFormFields($mutationName);
        return $fields;
    }

    // ──────────────────────────────────────────────────
    // COMPONENT INTROSPECTION (read columns/fields from presentation)
    // ──────────────────────────────────────────────────

    /**
     * Find column keys from the component that references this query
     */
    private function findComponentColumns(string $queryName): array
    {
        $pages = $this->packageData['presentation']['pages'] ?? $this->packageData['pages'] ?? [];
        foreach ($pages as $page) {
            if (!is_array($page)) continue;
            foreach ($page['components'] ?? [] as $component) {
                $q = $component['dataQuery'] ?? $component['query'] ?? '';
                if ($q !== $queryName) continue;

                // Extract column keys from config.columns
                $columns = $component['config']['columns'] ?? $component['columns'] ?? [];
                $keys = [];
                if (is_array($columns)) {
                    foreach ($columns as $col) {
                        if (is_array($col) && isset($col['key'])) {
                            $keys[] = $col['key'];
                        }
                    }
                }

                // For dashboard type, extract KPI keys
                if (($component['type'] ?? '') === 'dashboard') {
                    $kpis = $component['config']['kpis'] ?? $component['kpis'] ?? [];
                    if (is_array($kpis)) {
                        foreach ($kpis as $kpi) {
                            if (is_array($kpi) && isset($kpi['key'])) {
                                $keys[] = $kpi['key'];
                            }
                        }
                    }
                }

                // For detail type, extract section field keys
                if (($component['type'] ?? '') === 'detail') {
                    $sections = $component['config']['sections'] ?? $component['sections'] ?? [];
                    if (is_array($sections)) {
                        foreach ($sections as $section) {
                            if (!is_array($section)) continue;
                            foreach ($section['fields'] ?? [] as $field) {
                                if (is_array($field) && isset($field['key'])) {
                                    $keys[] = $field['key'];
                                }
                            }
                        }
                    }
                }

                if (!empty($keys)) {
                    return array_unique($keys);
                }
            }
        }
        return [];
    }

    /**
     * Find form field keys for a mutation
     */
    private function findFormFields(string $mutationName): array
    {
        $pages = $this->packageData['presentation']['pages'] ?? $this->packageData['pages'] ?? [];
        foreach ($pages as $page) {
            if (!is_array($page)) continue;
            foreach ($page['components'] ?? [] as $component) {
                if (($component['type'] ?? '') !== 'form') continue;
                $mutation = $component['mutation'] ?? $component['config']['mutation'] ?? '';
                if ($mutation !== $mutationName) continue;

                $fields = [];
                $sections = $component['config']['sections'] ?? $component['sections'] ?? [];
                foreach ($sections as $section) {
                    foreach ($section['fields'] ?? [] as $field) {
                        if (isset($field['key'])) {
                            $fields[] = $field['key'];
                        }
                    }
                }
                return $fields;
            }
        }
        return [];
    }

    // ──────────────────────────────────────────────────
    // TYPE INFERENCE (determine operation type from handler string)
    // ──────────────────────────────────────────────────

    /**
     * Infer query type from definition
     *
     * Checks explicit `type` first, then parses handler string pattern.
     */
    private function inferQueryType(array $queryDef): string
    {
        // Explicit type
        if (!empty($queryDef['type'])) {
            return $queryDef['type'];
        }

        $handler = $queryDef['handler'] ?? '';

        // Parse handler method name: "SomethingQueryHandler::methodName"
        $method = '';
        if (str_contains($handler, '::')) {
            [, $method] = explode('::', $handler, 2);
        } elseif (str_contains($handler, ':')) {
            [, $method] = explode(':', $handler, 2);
        } else {
            $method = $handler;
        }

        // Match method name patterns
        if (preg_match('/^list/i', $method)) return 'list';
        if (preg_match('/Stats$/i', $method)) return 'stats';
        if (preg_match('/Options$/i', $method)) return 'options';
        // getDepartments, getCampuses, getGrades → options (plural noun = dropdown list)
        if (preg_match('/^get[A-Z][a-z]+s$/i', $method) && !preg_match('/^get[A-Z][a-z]+Stats$/i', $method)) return 'options';
        if (preg_match('/^get/i', $method)) return 'get';
        if (preg_match('/^count/i', $method)) return 'count';

        // Default to list
        return 'list';
    }

    /**
     * Infer mutation type from definition or name
     */
    private function inferMutationType(array $mutationDef, string $mutationName): string
    {
        if (!empty($mutationDef['type'])) {
            return $mutationDef['type'];
        }

        $handler = $mutationDef['handler'] ?? $mutationName;
        $method = '';
        if (str_contains($handler, '::')) {
            [, $method] = explode('::', $handler, 2);
        } elseif (str_contains($handler, ':')) {
            [, $method] = explode(':', $handler, 2);
        } else {
            $method = $handler;
        }

        if (preg_match('/^create|^add|^insert/i', $method)) return 'create';
        if (preg_match('/^update|^edit|^modify|^correct/i', $method)) return 'update';
        if (preg_match('/^delete|^remove/i', $method)) return 'delete';
        if (preg_match('/^toggle/i', $method)) return 'toggle';
        if (preg_match('/^approve|^reject|^activate|^deactivate|^suspend/i', $method)) return 'status';

        return 'create'; // Default
    }

    /**
     * Infer toggle field name from mutation name
     *
     * e.g., "toggleOOS" → "oos_status" or "is_oos"
     */
    private function inferToggleField(string $mutationName): ?string
    {
        // Extract the field part: toggleX → x
        if (preg_match('/^toggle(.+)$/i', $mutationName, $m)) {
            $field = $m[1];
            // Convert camelCase to snake_case for DB column
            $snake = strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($field)));
            // Try common patterns
            $candidates = [
                'is_' . $snake,
                $snake . '_status',
                $snake,
            ];
            return $candidates[0]; // Default to is_xxx
        }

        return 'is_active';
    }

    /**
     * Infer new status from mutation name
     *
     * e.g., "approveFuelLog" → "approved"
     *        "rejectMaintenanceEvent" → "rejected"
     */
    private function inferStatus(string $mutationName): ?string
    {
        if (preg_match('/^approve/i', $mutationName)) return 'approved';
        if (preg_match('/^reject/i', $mutationName)) return 'rejected';
        if (preg_match('/^activate/i', $mutationName)) return 'active';
        if (preg_match('/^deactivate/i', $mutationName)) return 'inactive';
        if (preg_match('/^suspend/i', $mutationName)) return 'suspended';
        if (preg_match('/^archive/i', $mutationName)) return 'archived';
        if (preg_match('/^publish/i', $mutationName)) return 'published';
        if (preg_match('/^draft/i', $mutationName)) return 'draft';

        return null;
    }

    // ──────────────────────────────────────────────────
    // UTILITY METHODS
    // ──────────────────────────────────────────────────

    /**
     * Sanitize a column name to prevent SQL injection
     */
    private function sanitizeColumnName(string $name): string
    {
        // Allow only alphanumeric, underscore, dot (for table.column)
        return preg_replace('/[^a-zA-Z0-9_.]/', '', $name);
    }

    /**
     * Check if a table has a specific column
     */
    private function tableHasColumn(Database $db, string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;
        if (isset($this->tableColumns[$cacheKey])) {
            return $this->tableColumns[$cacheKey];
        }

        try {
            $stmt = $db->fetchAll("SHOW COLUMNS FROM {$table} LIKE ?", [$column]);
            $exists = !empty($stmt);
            $this->tableColumns[$cacheKey] = $exists;
            return $exists;
        } catch (\Exception $e) {
            $this->tableColumns[$cacheKey] = false;
            return false;
        }
    }

    /**
     * Toggle a value between common on/off representations
     */
    private function toggleValue($value): mixed
    {
        if ($value === 1 || $value === '1') return 0;
        if ($value === 0 || $value === '0') return 1;
        if ($value === 'active') return 'inactive';
        if ($value === 'inactive') return 'active';
        if ($value === 'enabled') return 'disabled';
        if ($value === 'disabled') return 'enabled';
        if ($value === 'yes') return 'no';
        if ($value === 'no') return 'yes';
        if ($value === true) return false;
        if ($value === false) return true;
        return !$value;
    }

    /**
     * Build empty result with metadata
     */
    private function emptyResult(array $params, string $message = ''): array
    {
        return [
            'data' => [],
            'meta' => [
                'total' => 0,
                'page' => (int)($params['page'] ?? 1),
                'perPage' => (int)($params['pageSize'] ?? self::DEFAULT_PAGE_SIZE),
                'totalPages' => 0,
            ],
            '_message' => $message,
        ];
    }

    /**
     * Log mutation to audit system
     */
    private function auditLog(string $mutationName, array $input, array $context, array $result): void
    {
        try {
            $userId = $context['user']['id'] ?? 0;
            $action = "package.{$this->packageId}.{$mutationName}";
            $target = $result['data']['id'] ?? null;

            AuditLogger::log(
                $action,
                $userId,
                $target ? "Record ID: {$target}" : 'Package mutation executed',
                ['input' => $input, 'result' => $result]
            );
        } catch (\Exception $e) {
            error_log("[GenericPackageHandler] Audit log failed: " . $e->getMessage());
        }
    }
}
