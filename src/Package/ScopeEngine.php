<?php

namespace Hub\Package;

/**
 * Scope Engine
 * 
 * Applies row-level security filters to database queries.
 * Restricts data access based on user context (department, ownership, etc.).
 * 
 * Version: v0 - Basic scoping support (Sprint 0)
 * 
 * @see PACKAGE_ARCHITECTURE_SPEC.md §12 (Enforcement Pipelines)
 */
class ScopeEngine
{
    /**
     * Apply scope filters to query builder
     * 
     * Converts scope filters from PolicyEngine into SQL WHERE clauses.
     * 
     * @param \Spatie\QueryBuilder\QueryBuilder|object $query Laravel query builder instance
     * @param array $scopeFilters Scope filters from PolicyEngine::getScopeFilters():
     *   [
     *     ['column' => 'department_id', 'operator' => '=', 'value' => 123],
     *     ['column' => 'created_by', 'operator' => '=', 'value' => $userId]
     *   ]
     * 
     * @return \Spatie\QueryBuilder\QueryBuilder|object Modified query builder
     */
    public function applyFilters($query, array $scopeFilters)
    {
        foreach ($scopeFilters as $filter) {
            if (!isset($filter['column']) || !isset($filter['operator']) || !isset($filter['value'])) {
                continue; // Skip invalid filters
            }

            $column = $filter['column'];
            $operator = $filter['operator'];
            $value = $filter['value'];

            // Apply filter to query
            $query->where($column, $operator, $value);
        }

        return $query;
    }

    /**
     * Apply scope filters to raw SQL WHERE clause
     * 
     * For handlers that don't use query builder (raw SQL).
     * 
     * @param array $scopeFilters Scope filters from PolicyEngine
     * 
     * @return array ['sql' => 'WHERE clause', 'bindings' => [...]]
     */
    public function toWhereClause(array $scopeFilters): array
    {
        if (empty($scopeFilters)) {
            return ['sql' => '', 'bindings' => []];
        }

        $conditions = [];
        $bindings = [];

        foreach ($scopeFilters as $filter) {
            if (!isset($filter['column']) || !isset($filter['operator']) || !isset($filter['value'])) {
                continue;
            }

            $column = $filter['column'];
            $operator = $filter['operator'];
            $value = $filter['value'];

            $conditions[] = "{$column} {$operator} ?";
            $bindings[] = $value;
        }

        if (empty($conditions)) {
            return ['sql' => '', 'bindings' => []];
        }

        $sql = 'WHERE ' . implode(' AND ', $conditions);

        return ['sql' => $sql, 'bindings' => $bindings];
    }

    /**
     * Validate scope filter format
     * 
     * @param array $filter Scope filter to validate
     * 
     * @return bool True if valid
     */
    public function isValidFilter(array $filter): bool
    {
        if (!isset($filter['column']) || !isset($filter['operator']) || !isset($filter['value'])) {
            return false;
        }

        // Validate operator
        $validOperators = ['=', '!=', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN'];
        if (!in_array(strtoupper($filter['operator']), $validOperators)) {
            return false;
        }

        // Validate column name (prevent SQL injection)
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $filter['column'])) {
            return false;
        }

        return true;
    }

    /**
     * Get statistics about applied filters
     * 
     * @param array $scopeFilters Scope filters
     * 
     * @return array Statistics
     */
    public function getStats(array $scopeFilters): array
    {
        $stats = [
            'totalFilters' => count($scopeFilters),
            'validFilters' => 0,
            'invalidFilters' => 0,
            'columns' => [],
        ];

        foreach ($scopeFilters as $filter) {
            if ($this->isValidFilter($filter)) {
                $stats['validFilters']++;
                if (isset($filter['column'])) {
                    $stats['columns'][] = $filter['column'];
                }
            } else {
                $stats['invalidFilters']++;
            }
        }

        $stats['columns'] = array_unique($stats['columns']);

        return $stats;
    }
}
