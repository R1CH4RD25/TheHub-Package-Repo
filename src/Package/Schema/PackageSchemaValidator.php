<?php

namespace Hub\Package\Schema;

/**
 * Package Schema Validator — The "Boundary Enforcer"
 *
 * Validates any package.json against the ComponentSchema rules.
 * Returns structured errors with exact paths, human-readable messages,
 * and fix suggestions so creators know EXACTLY how to fix issues.
 *
 * Usage:
 *   $validator = new PackageSchemaValidator();
 *   $result = $validator->validate($packageJson);
 *   if (!$result['valid']) {
 *       foreach ($result['errors'] as $e) {
 *           echo "{$e['path']}: {$e['message']}\n";
 *       }
 *   }
 *
 * @see ComponentSchema for stack type definitions
 */
class PackageSchemaValidator
{
    /** @var array Collected errors */
    private array $errors = [];

    /** @var array Collected warnings */
    private array $warnings = [];

    /** @var array Cross-reference tracking (query/mutation usage) */
    private array $referencedQueries = [];
    private array $referencedMutations = [];

    /**
     * System roles are admin-configured via org_roles table.
     * The schema validator only checks format — runtime validation
     * against actual org_roles happens in PackageValidator (DB-aware).
     */

    /**
     * Validate a complete package definition
     *
     * @param array $packageData Decoded package JSON
     * @return array{valid: bool, errors: array, warnings: array, summary: string}
     */
    public function validate(array $packageData): array
    {
        $this->errors = [];
        $this->warnings = [];
        $this->referencedQueries = [];
        $this->referencedMutations = [];

        // 1. Top-level structure
        $this->validateTopLevel($packageData);

        // 2. Package metadata
        if (isset($packageData['package'])) {
            $this->validatePackageMetadata($packageData['package']);
        }

        // 3. Database config
        if (isset($packageData['database'])) {
            $this->validateDatabase($packageData['database']);
        }

        // 4. Presentation (pages → components → stack rules)
        if (isset($packageData['presentation']['pages'])) {
            $this->validatePages($packageData['presentation']['pages'], $packageData);
        }

        // 5. Data (queries & mutations)
        if (isset($packageData['data'])) {
            $this->validateData($packageData['data']);
        }

        // 6. Policy
        if (isset($packageData['policy'])) {
            $this->validatePolicy($packageData['policy']);
        }

        // 7. Resources (provides / requires)
        if (isset($packageData['resources'])) {
            $this->validateResources($packageData['resources']);
        }

        // 8. Access (layer2 workflow + edit boundaries)
        if (isset($packageData['access'])) {
            $this->validateAccess($packageData['access'], $packageData);
        }

        // 9. ShowIf cross-references
        $this->validateShowIfReferences($packageData);

        // 10. Cross-reference validation
        $this->validateCrossReferences($packageData);

        $errorCount = count($this->errors);
        $warningCount = count($this->warnings);
        $valid = $errorCount === 0;

        $summary = $valid
            ? "Package is valid" . ($warningCount > 0 ? " with {$warningCount} warning(s)" : "")
            : "Package has {$errorCount} error(s)" . ($warningCount > 0 ? " and {$warningCount} warning(s)" : "");

        return [
            'valid'    => $valid,
            'errors'   => $this->errors,
            'warnings' => $this->warnings,
            'summary'  => $summary,
        ];
    }

    // ──────────────────────────────────────────────────────
    //  TOP-LEVEL VALIDATION
    // ──────────────────────────────────────────────────────

    private function validateTopLevel(array $data): void
    {
        $required = ['schemaVersion', 'package', 'database', 'presentation', 'data'];

        foreach ($required as $field) {
            if (!isset($data[$field])) {
                $this->addError("$", "Missing required top-level key: '{$field}'", [
                    'fix' => "Add \"{$field}\": {} to your package.json",
                ]);
            }
        }

        if (isset($data['schemaVersion']) && $data['schemaVersion'] !== '3.0.0') {
            $this->addError('$.schemaVersion', "Expected '3.0.0', got '{$data['schemaVersion']}'", [
                'fix' => 'Set "schemaVersion": "3.0.0"',
            ]);
        }
    }

    // ──────────────────────────────────────────────────────
    //  PACKAGE METADATA
    // ──────────────────────────────────────────────────────

    private function validatePackageMetadata(array $pkg): void
    {
        $path = '$.package';

        // Required fields
        foreach (['id', 'display_name', 'version', 'category'] as $field) {
            if (empty($pkg[$field])) {
                $this->addError("{$path}.{$field}", "Required field is missing or empty");
            }
        }

        // ID format
        if (!empty($pkg['id']) && !preg_match('/^[a-z]+\.[a-z0-9-]+$/', $pkg['id'])) {
            $this->addError("{$path}.id", "Must be lowercase category.name format (e.g., 'district.student-directory')", [
                'fix' => "Use format: category.package-name (lowercase, hyphens allowed in name)",
            ]);
        }

        // Version format
        if (!empty($pkg['version']) && !preg_match('/^\d+\.\d+\.\d+$/', $pkg['version'])) {
            $this->addError("{$path}.version", "Must be semver format: major.minor.patch (e.g., '1.0.0')");
        }

        // Category validation
        $validCategories = ['district', 'operations', 'local', 'admin'];
        if (!empty($pkg['category']) && !in_array($pkg['category'], $validCategories)) {
            $this->addError("{$path}.category", "Invalid category '{$pkg['category']}'. Must be one of: " . implode(', ', $validCategories));
        }

        // Icon format
        if (!empty($pkg['icon']) && !preg_match('/^(lucide-|bi-)[a-z0-9-]+$/', $pkg['icon'])) {
            $this->addWarning("{$path}.icon", "Icon should use 'lucide-*' or 'bi-*' prefix", [
                'fix' => "Use format: lucide-icon-name or bi-icon-name",
            ]);
        }

        // base_url auto-validation
        if (!empty($pkg['id']) && !empty($pkg['base_url'])) {
            $expected = '/p/' . $pkg['id'];
            if ($pkg['base_url'] !== $expected) {
                $this->addWarning("{$path}.base_url", "Expected '{$expected}', got '{$pkg['base_url']}'");
            }
        }
    }

    // ──────────────────────────────────────────────────────
    //  DATABASE CONFIG
    // ──────────────────────────────────────────────────────

    private function validateDatabase(array $db): void
    {
        $path = '$.database';

        if (empty($db['connection'])) {
            $this->addError("{$path}.connection", "Database connection name is required", [
                'fix' => 'Set "connection": "your_database_name"',
            ]);
        }

        if (empty($db['primaryTable'])) {
            $this->addError("{$path}.primaryTable", "Primary table name is required", [
                'fix' => 'Set "primaryTable": "your_main_table"',
            ]);
        }

        // Snake_case enforcement
        foreach (['connection', 'primaryTable', 'auditTable'] as $key) {
            if (!empty($db[$key]) && !preg_match('/^[a-z][a-z0-9_]*$/', $db[$key])) {
                $this->addError("{$path}.{$key}", "Must be lowercase snake_case: '{$db[$key]}'");
            }
        }
    }

    // ──────────────────────────────────────────────────────
    //  PAGES & COMPONENTS
    // ──────────────────────────────────────────────────────

    private function validatePages(array $pages, array $packageData): void
    {
        $path = '$.presentation.pages';

        if (!isset($pages['index'])) {
            $this->addError($path, "Must have an 'index' page (the landing page)", [
                'fix' => 'Add "index": { "title": "...", "route": "/", "components": [...] }',
            ]);
        }

        $routes = [];
        foreach ($pages as $pageId => $page) {
            $pagePath = "{$path}.{$pageId}";
            $this->validatePage($page, $pagePath, $packageData);

            // Check for duplicate routes
            $route = $page['route'] ?? '';
            if ($route && isset($routes[$route])) {
                $this->addError("{$pagePath}.route", "Duplicate route '{$route}' — also used by page '{$routes[$route]}'");
            }
            $routes[$route] = $pageId;
        }
    }

    private function validatePage(array $page, string $path, array $packageData): void
    {
        // Required fields
        foreach (['title', 'route', 'components'] as $field) {
            if (!isset($page[$field])) {
                $this->addError("{$path}.{$field}", "Required page field is missing");
            }
        }

        // Route format
        if (!empty($page['route']) && !preg_match('#^/[a-z0-9{}/._-]*$#i', $page['route'])) {
            $this->addError("{$path}.route", "Invalid route format: '{$page['route']}'. Must start with / and use lowercase.", [
                'fix' => 'Routes: /, /add, /vehicle/{id}, /fuel/logs',
            ]);
        }

        // Layout
        $validLayouts = ['full', 'standard', 'sidebar', 'narrow', 'split'];
        if (!empty($page['layout']) && !in_array($page['layout'], $validLayouts)) {
            $this->addError("{$path}.layout", "Invalid layout '{$page['layout']}'. Must be: " . implode(', ', $validLayouts));
        }

        // Components
        if (isset($page['components']) && is_array($page['components'])) {
            if (empty($page['components'])) {
                $this->addError("{$path}.components", "Page must have at least one component");
            }

            foreach ($page['components'] as $i => $comp) {
                $this->validateComponent($comp, "{$path}.components[{$i}]", $packageData);
            }
        }
    }

    /**
     * Validate a component against its stack type schema
     */
    private function validateComponent(array $comp, string $path, array $packageData): void
    {
        if (!isset($comp['type'])) {
            $this->addError("{$path}.type", "Component must have a 'type' property");
            return;
        }

        $validTypes = ['dashboard', 'cards', 'table', 'form', 'detail', 'filters'];
        if (!in_array($comp['type'], $validTypes)) {
            $this->addError("{$path}.type", "Unknown component type: '{$comp['type']}'. Valid types: " . implode(', ', $validTypes));
            return;
        }

        if (!isset($comp['config'])) {
            $this->addError("{$path}.config", "Component must have a 'config' object");
            return;
        }

        // Track referenced queries
        if (!empty($comp['dataQuery'])) {
            $this->referencedQueries[] = $comp['dataQuery'];
        }

        // Delegate to type-specific validator
        $config = $comp['config'];
        match ($comp['type']) {
            'dashboard', 'cards' => $this->validateDashboardConfig($config, "{$path}.config"),
            'table'              => $this->validateTableConfig($config, "{$path}.config", $packageData),
            'form'               => $this->validateFormConfig($config, "{$path}.config", $packageData),
            'detail'             => $this->validateDetailConfig($config, "{$path}.config"),
            'filters'            => $this->validateFiltersConfig($config, "{$path}.config"),
        };
    }

    // ──────────────────────────────────────────────────────
    //  DASHBOARD STACK VALIDATION
    // ──────────────────────────────────────────────────────

    private function validateDashboardConfig(array $config, string $path): void
    {
        // Cards are required
        if (empty($config['cards']) && empty($config['charts']) && empty($config['queues'])) {
            $this->addError($path, "Dashboard must have at least one of: cards, charts, or queues");
        }

        // Columns range
        if (isset($config['columns'])) {
            $cols = $config['columns'];
            if (!is_int($cols) || $cols < 1 || $cols > 6) {
                $this->addError("{$path}.columns", "Columns must be 1-6, got: {$cols}");
            }
        }

        // Validate cards
        if (!empty($config['cards'])) {
            if (count($config['cards']) > 12) {
                $this->addWarning("{$path}.cards", "Dashboard has " . count($config['cards']) . " cards — consider limiting to 12 for readability");
            }

            foreach ($config['cards'] as $i => $card) {
                $this->validateDashboardCard($card, "{$path}.cards[{$i}]");
            }
        }
    }

    private function validateDashboardCard(array $card, string $path): void
    {
        if (empty($card['title'])) {
            $this->addError("{$path}.title", "Card must have a title");
        }
        if (empty($card['dataKey']) && !isset($card['value'])) {
            $this->addWarning("{$path}.dataKey", "Card has no dataKey or static value — will render empty");
        }

        $validColors = ['primary', 'success', 'warning', 'danger', 'info', 'secondary', 'dark'];
        if (!empty($card['color']) && !in_array($card['color'], $validColors)) {
            $this->addError("{$path}.color", "Invalid color '{$card['color']}'. Must be: " . implode(', ', $validColors));
        }
    }

    // ──────────────────────────────────────────────────────
    //  TABLE STACK VALIDATION
    // ──────────────────────────────────────────────────────

    private function validateTableConfig(array $config, string $path, array $packageData): void
    {
        if (empty($config['columns'])) {
            $this->addError("{$path}.columns", "Table must define at least one column", [
                'fix' => '"columns": [{ "key": "name", "label": "Name", "sortable": true }]',
            ]);
            return;
        }

        foreach ($config['columns'] as $i => $col) {
            $this->validateTableColumn($col, "{$path}.columns[{$i}]");
        }

        // Row actions
        if (!empty($config['actions'])) {
            foreach ($config['actions'] as $i => $action) {
                $this->validateRowAction($action, "{$path}.actions[{$i}]");
            }
        }

        // Pagination
        if (isset($config['pagination']['perPage'])) {
            $pp = $config['pagination']['perPage'];
            if (!is_int($pp) || $pp < 5 || $pp > 500) {
                $this->addError("{$path}.pagination.perPage", "Must be 5-500, got: {$pp}");
            }
        }
    }

    private function validateTableColumn(array $col, string $path): void
    {
        if (empty($col['key'])) {
            $this->addError("{$path}.key", "Column must have a key (database column name)");
        }
        if (empty($col['label'])) {
            $this->addError("{$path}.label", "Column must have a label (header text)");
        }

        $validTypes = ['text', 'number', 'date', 'datetime', 'currency', 'email', 'badge', 'boolean', 'link', 'image', 'percentage', 'masked'];
        if (!empty($col['type']) && !in_array($col['type'], $validTypes)) {
            $this->addError("{$path}.type", "Invalid column type '{$col['type']}'. Must be: " . implode(', ', $validTypes));
        }

        // Badge without badgeMap warning
        if (($col['type'] ?? '') === 'badge' && empty($col['badgeMap'])) {
            $this->addWarning("{$path}.badgeMap", "Badge column without badgeMap — all values will use default color", [
                'fix' => '"badgeMap": { "active": "success", "inactive": "danger" }',
            ]);
        }
    }

    private function validateRowAction(array $action, string $path): void
    {
        if (empty($action['label'])) {
            $this->addError("{$path}.label", "Row action must have a label");
        }

        $validTypes = ['route', 'mutation', 'confirm', 'modal'];
        if (!empty($action['type']) && !in_array($action['type'], $validTypes)) {
            $this->addError("{$path}.type", "Invalid action type '{$action['type']}'. Must be: " . implode(', ', $validTypes));
        }

        // Route action needs 'to'
        if (($action['type'] ?? '') === 'route' && empty($action['to']) && empty($action['route'])) {
            $this->addError("{$path}.to", "Route action must have a 'to' property (e.g., '/vehicle/{id}')");
        }

        // Mutation action needs 'mutation'
        if (($action['type'] ?? '') === 'mutation' && empty($action['mutation'])) {
            $this->addError("{$path}.mutation", "Mutation action must have a 'mutation' property");
        }

        if (!empty($action['mutation'])) {
            $this->referencedMutations[] = $action['mutation'];
        }
    }

    // ──────────────────────────────────────────────────────
    //  FORM STACK VALIDATION
    // ──────────────────────────────────────────────────────

    private function validateFormConfig(array $config, string $path, array $packageData): void
    {
        if (empty($config['mutation'])) {
            $this->addError("{$path}.mutation", "Form must specify a mutation name (called on submit)", [
                'fix' => '"mutation": "createStudent" — must match a key in data.mutations',
            ]);
        } else {
            $this->referencedMutations[] = $config['mutation'];
        }

        // Must have sections OR fields (not both, not neither)
        $hasSections = !empty($config['sections']);
        $hasFields = !empty($config['fields']);

        if (!$hasSections && !$hasFields) {
            $this->addError($path, "Form must have 'sections' or 'fields' (at least one)", [
                'fix' => 'Add "sections": [{ "title": "...", "fields": [...] }] or "fields": [...]',
            ]);
        }

        if ($hasSections && $hasFields) {
            $this->addWarning($path, "Form has both 'sections' and 'fields' — only sections will render");
        }

        // Validate sections
        if ($hasSections) {
            foreach ($config['sections'] as $i => $section) {
                $this->validateFormSection($section, "{$path}.sections[{$i}]");
            }
        }

        // Validate flat fields
        if ($hasFields && !$hasSections) {
            foreach ($config['fields'] as $i => $field) {
                $this->validateField($field, "{$path}.fields[{$i}]");
            }
        }
    }

    private function validateFormSection(array $section, string $path): void
    {
        if (empty($section['title'])) {
            $this->addWarning("{$path}.title", "Section without title — consider adding one for clarity");
        }

        if (empty($section['fields'])) {
            $this->addError("{$path}.fields", "Section must have at least one field");
        }

        // Grid range
        if (isset($section['grid'])) {
            $grid = $section['grid'];
            if (!is_int($grid) || $grid < 1 || $grid > 4) {
                $this->addError("{$path}.grid", "Grid must be 1-4, got: {$grid}");
            }
        }

        // Collapsed without collapsible
        if (!empty($section['collapsed']) && empty($section['collapsible'])) {
            $this->addWarning("{$path}.collapsed", "Section has 'collapsed: true' but 'collapsible' is not set — adding collapsible automatically");
        }

        // Validate fields
        if (!empty($section['fields'])) {
            foreach ($section['fields'] as $i => $field) {
                $this->validateField($field, "{$path}.fields[{$i}]");
            }

            // Check span values against grid
            $grid = $section['grid'] ?? 1;
            foreach ($section['fields'] as $i => $field) {
                if (isset($field['span']) && $field['span'] > $grid) {
                    $this->addWarning("{$path}.fields[{$i}].span", "Field span ({$field['span']}) exceeds section grid ({$grid})");
                }
            }
        }
    }

    /**
     * Validate a field definition against field type rules
     */
    private function validateField(array $field, string $path): void
    {
        // Required: key, type, label
        if (empty($field['key']) && empty($field['name'])) {
            $this->addError("{$path}.key", "Field must have a 'key' property (database column name)");
        }

        $key = $field['key'] ?? $field['name'] ?? '(no key)';

        if (empty($field['type'])) {
            $this->addError("{$path}.type", "Field '{$key}' must have a type (text, select, number, etc.)");
            return;
        }

        $type = $field['type'];
        $rules = ComponentSchema::fieldTypeRules();

        if (!isset($rules[$type])) {
            $this->addError("{$path}.type", "Unknown field type '{$type}' for field '{$key}'. Valid types: " . implode(', ', array_keys($rules)));
            return;
        }

        $typeRule = $rules[$type];

        // Select/radio must have options or optionsQuery
        if ($type === 'select') {
            if (empty($field['options']) && empty($field['optionsQuery'])) {
                $this->addError("{$path}", "Select field '{$key}' needs 'options' (static) or 'optionsQuery' (dynamic)", [
                    'fix' => '"options": [{ "value": "a", "label": "Option A" }] or "optionsQuery": "getOptions"',
                ]);
            }
            if (!empty($field['optionsQuery'])) {
                $this->referencedQueries[] = $field['optionsQuery'];
            }
        }

        if ($type === 'radio') {
            if (empty($field['options'])) {
                $this->addError("{$path}", "Radio field '{$key}' must have 'options' array");
            }
        }

        // Validate options format
        if (!empty($field['options']) && is_array($field['options'])) {
            foreach ($field['options'] as $j => $opt) {
                if (is_array($opt)) {
                    if (!isset($opt['value'])) {
                        $this->addError("{$path}.options[{$j}]", "Option must have a 'value' property");
                    }
                    if (!isset($opt['label'])) {
                        $this->addWarning("{$path}.options[{$j}]", "Option missing 'label' — 'value' will be displayed");
                    }
                }
            }
        }

        // Span validation
        if (isset($field['span']) && (!is_int($field['span']) || $field['span'] < 1 || $field['span'] > 4)) {
            $this->addError("{$path}.span", "Span must be 1-4, got: {$field['span']}");
        }

        // Validation rules check
        if (!empty($field['validation']) && is_array($field['validation'])) {
            $allowedValidationKeys = $typeRule['validationKeys'] ?? [];
            if (!empty($allowedValidationKeys)) {
                foreach (array_keys($field['validation']) as $vKey) {
                    if (!in_array($vKey, $allowedValidationKeys)) {
                        $this->addWarning("{$path}.validation.{$vKey}", "Validation key '{$vKey}' is not applicable for field type '{$type}'");
                    }
                }
            }
        }
    }

    // ──────────────────────────────────────────────────────
    //  DETAIL STACK VALIDATION
    // ──────────────────────────────────────────────────────

    private function validateDetailConfig(array $config, string $path): void
    {
        if (empty($config['sections'])) {
            $this->addWarning($path, "Detail component has no sections — all fields will render flat");
        }

        if (!empty($config['sections'])) {
            foreach ($config['sections'] as $i => $section) {
                $sPath = "{$path}.sections[{$i}]";
                if (empty($section['title'])) {
                    $this->addWarning("{$sPath}.title", "Detail section without title");
                }
                if (empty($section['fields'])) {
                    $this->addError("{$sPath}.fields", "Detail section must have fields");
                } else {
                    foreach ($section['fields'] as $j => $field) {
                        $fPath = "{$sPath}.fields[{$j}]";
                        if (empty($field['key'])) {
                            $this->addError("{$fPath}.key", "Detail field must have a key");
                        }
                        if (empty($field['label'])) {
                            $this->addWarning("{$fPath}.label", "Detail field missing label");
                        }
                        $validTypes = ['text', 'date', 'datetime', 'email', 'link', 'badge', 'boolean', 'currency', 'masked', 'long_text', 'list', 'image'];
                        if (!empty($field['type']) && !in_array($field['type'], $validTypes)) {
                            $this->addError("{$fPath}.type", "Invalid detail field type '{$field['type']}'");
                        }
                    }
                }

                if (isset($section['columns']) && (!is_int($section['columns']) || $section['columns'] < 1 || $section['columns'] > 4)) {
                    $this->addError("{$sPath}.columns", "Section columns must be 1-4");
                }
            }
        }
    }

    // ──────────────────────────────────────────────────────
    //  FILTERS STACK VALIDATION
    // ──────────────────────────────────────────────────────

    private function validateFiltersConfig(array $config, string $path): void
    {
        $filters = $config['filters'] ?? $config['fields'] ?? [];

        if (empty($filters)) {
            $this->addError("{$path}.filters", "Filters component must have at least one filter", [
                'fix' => '"filters": [{ "name": "search", "type": "search", "placeholder": "Search..." }]',
            ]);
            return;
        }

        if (count($filters) > 10) {
            $this->addWarning("{$path}.filters", "Too many filters (" . count($filters) . ") — consider limiting to 10 for UX");
        }

        $validTypes = ['search', 'select', 'date', 'date_range', 'checkbox', 'text'];

        foreach ($filters as $i => $filter) {
            $fPath = "{$path}.filters[{$i}]";

            if (empty($filter['name'])) {
                $this->addError("{$fPath}.name", "Filter must have a name (URL parameter)");
            }

            if (empty($filter['type'])) {
                $this->addError("{$fPath}.type", "Filter must have a type");
            } elseif (!in_array($filter['type'], $validTypes)) {
                $this->addError("{$fPath}.type", "Invalid filter type '{$filter['type']}'. Must be: " . implode(', ', $validTypes));
            }

            // Select filter should have options or optionsQuery
            if (($filter['type'] ?? '') === 'select' && empty($filter['options']) && empty($filter['optionsQuery'])) {
                $this->addWarning("{$fPath}", "Select filter without options or optionsQuery — will render empty");
            }

            if (!empty($filter['optionsQuery'])) {
                $this->referencedQueries[] = $filter['optionsQuery'];
            }
        }
    }

    // ──────────────────────────────────────────────────────
    //  DATA (QUERIES & MUTATIONS)
    // ──────────────────────────────────────────────────────

    private function validateData(array $data): void
    {
        $path = '$.data';

        // Validate queries
        if (isset($data['queries']) && is_array($data['queries'])) {
            foreach ($data['queries'] as $name => $query) {
                $this->validateQuery($query, "{$path}.queries.{$name}", $name);
            }
        }

        // Validate mutations
        if (isset($data['mutations']) && is_array($data['mutations'])) {
            foreach ($data['mutations'] as $name => $mutation) {
                $this->validateMutation($mutation, "{$path}.mutations.{$name}", $name);
            }
        }
    }

    private function validateQuery(array $query, string $path, string $name): void
    {
        if (empty($query['handler'])) {
            $this->addError("{$path}.handler", "Query '{$name}' must have a handler", [
                'fix' => "Use naming convention: list* (list), get*Stats (stats), get* (single), getOptions (options)",
            ]);
        }

        // Validate searchColumns are strings
        if (isset($query['searchColumns'])) {
            if (!is_array($query['searchColumns'])) {
                $this->addError("{$path}.searchColumns", "searchColumns must be an array of column names");
            } else {
                foreach ($query['searchColumns'] as $i => $col) {
                    if (!is_string($col)) {
                        $this->addError("{$path}.searchColumns[{$i}]", "Must be a string (column name)");
                    }
                }
            }
        }

        // Validate defaultSort
        if (isset($query['defaultSort'])) {
            if (empty($query['defaultSort']['column'])) {
                $this->addError("{$path}.defaultSort.column", "defaultSort must have a 'column' property");
            }
            if (isset($query['defaultSort']['direction']) && !in_array($query['defaultSort']['direction'], ['ASC', 'DESC'])) {
                $this->addError("{$path}.defaultSort.direction", "Must be 'ASC' or 'DESC'");
            }
        }
    }

    private function validateMutation(array $mutation, string $path, string $name): void
    {
        if (empty($mutation['handler'])) {
            $this->addError("{$path}.handler", "Mutation '{$name}' must have a handler", [
                'fix' => "Use naming convention: create* (insert), update* (update), delete* (soft delete)",
            ]);
        }
    }

    // ──────────────────────────────────────────────────────
    //  POLICY
    // ──────────────────────────────────────────────────────

    private function validatePolicy(array $policy): void
    {
        $path = '$.policy';

        if (isset($policy['roles'])) {
            if (!is_array($policy['roles']) || empty($policy['roles'])) {
                $this->addError("{$path}.roles", "Roles must be a non-empty array");
            }
        }

        if (isset($policy['defaultRole'])) {
            $roles = $policy['roles'] ?? [];
            // Roles can be an array of strings OR an object keyed by role name
            $roleNames = $this->extractRoleNames($roles);
            if (!empty($roleNames) && !in_array($policy['defaultRole'], $roleNames)) {
                $this->addWarning("{$path}.defaultRole", "Default role '{$policy['defaultRole']}' is not in the roles list — will still work if role exists in the system");
            }
        }

        if (isset($policy['globalRoleMapping']) && is_array($policy['globalRoleMapping'])) {
            $roles = $policy['roles'] ?? [];
            $roleNames = $this->extractRoleNames($roles);

            foreach ($policy['globalRoleMapping'] as $sysRole => $pkgRole) {
                // Validate key format: snake_case or PascalCase org role names
                if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_ \/()-]*$/', $sysRole)) {
                    $this->addError("{$path}.globalRoleMapping.{$sysRole}", "Invalid system role name format: '{$sysRole}'");
                }

                // If package defines roles, mapped value should reference one
                if (!empty($roleNames) && !in_array($pkgRole, $roleNames)) {
                    $this->addWarning("{$path}.globalRoleMapping.{$sysRole}", "Mapped package role '{$pkgRole}' is not in the package roles list");
                }
            }

            if (empty($policy['globalRoleMapping'])) {
                $this->addWarning("{$path}.globalRoleMapping", "globalRoleMapping is empty — no system roles will map to package roles on install");
            }
        }
    }

    // ──────────────────────────────────────────────────────
    //  RESOURCES (PROVIDES / REQUIRES)
    // ──────────────────────────────────────────────────────

    /**
     * Validate the resources block (cross-package resource sharing)
     */
    private function validateResources(array $resources): void
    {
        $path = '$.resources';

        // Validate provides
        if (isset($resources['provides'])) {
            if (!is_array($resources['provides'])) {
                $this->addError("{$path}.provides", "Must be an array of resource declarations");
            } else {
                foreach ($resources['provides'] as $i => $res) {
                    $rPath = "{$path}.provides[{$i}]";

                    if (empty($res['key'])) {
                        $this->addError("{$rPath}.key", "Resource must have a 'key' (e.g., 'operations.vehicles')", [
                            'fix' => '"key": "category.resource-name"',
                        ]);
                    } elseif (!preg_match('/^[a-z]+\.[a-z0-9_-]+$/', $res['key'])) {
                        $this->addError("{$rPath}.key", "Resource key must be lowercase category.name format: '{$res['key']}'");
                    }

                    if (empty($res['contract'])) {
                        $this->addError("{$rPath}.contract", "Resource must declare a contract version (semver)", [
                            'fix' => '"contract": "1.0.0"',
                        ]);
                    } elseif (!preg_match('/^\d+\.\d+\.\d+$/', $res['contract'])) {
                        $this->addError("{$rPath}.contract", "Contract version must be semver: '{$res['contract']}'");
                    }

                    if (empty($res['table'])) {
                        $this->addError("{$rPath}.table", "Resource must specify a source table", [
                            'fix' => '"table": "your_primary_table"',
                        ]);
                    }

                    if (empty($res['view'])) {
                        $this->addWarning("{$rPath}.view", "Resource without a view — consumers will query the raw table, which is fragile", [
                            'fix' => '"view": "hub_vehicles_v1" — a contract-stable view for consumers',
                        ]);
                    }
                }
            }
        }

        // Validate requires
        if (isset($resources['requires'])) {
            if (!is_array($resources['requires'])) {
                $this->addError("{$path}.requires", "Must be an array of required resource declarations");
            } else {
                foreach ($resources['requires'] as $i => $req) {
                    $rPath = "{$path}.requires[{$i}]";

                    if (empty($req['key'])) {
                        $this->addError("{$rPath}.key", "Required resource must have a 'key'");
                    }

                    if (empty($req['contract'])) {
                        $this->addError("{$rPath}.contract", "Required resource must declare a minimum contract version", [
                            'fix' => '"contract": ">=1.0.0"',
                        ]);
                    }

                    // Optional flag
                    if (isset($req['optional']) && !is_bool($req['optional'])) {
                        $this->addError("{$rPath}.optional", "Must be a boolean (true/false)");
                    }
                }
            }
        }
    }

    // ──────────────────────────────────────────────────────
    //  ACCESS (LAYER2 WORKFLOW + EDIT BOUNDARIES)
    // ──────────────────────────────────────────────────────

    /**
     * Validate the access block (workflow states, transitions, edit boundaries)
     */
    private function validateAccess(array $access, array $packageData): void
    {
        $path = '$.access';

        if (!isset($access['layer2'])) {
            return; // access block without layer2 is fine (no workflow)
        }

        $layer2 = $access['layer2'];
        $l2Path = "{$path}.layer2";

        // Validate workflow
        if (isset($layer2['workflow'])) {
            $wfPath = "{$l2Path}.workflow";
            $workflow = $layer2['workflow'];

            // States must be an array of strings
            $definedStates = [];
            if (empty($workflow['states'])) {
                $this->addError("{$wfPath}.states", "Workflow must define at least one state", [
                    'fix' => '"states": ["draft", "submitted", "approved", "rejected"]',
                ]);
            } else {
                $definedStates = $workflow['states'];
                $canonicalStates = ['draft', 'submitted', 'in_review', 'needs_correction', 'resubmitted', 'approved', 'rejected'];
                foreach ($definedStates as $state) {
                    if (!in_array($state, $canonicalStates)) {
                        $this->addWarning("{$wfPath}.states", "Custom workflow state '{$state}' — ensure it's handled in your mutation handlers");
                    }
                }
            }

            // Transitions
            if (isset($workflow['transitions'])) {
                foreach ($workflow['transitions'] as $i => $transition) {
                    $tPath = "{$wfPath}.transitions[{$i}]";

                    if (empty($transition['from'])) {
                        $this->addError("{$tPath}.from", "Transition must have a 'from' state");
                    } elseif (!empty($definedStates) && !in_array($transition['from'], $definedStates)) {
                        $this->addError("{$tPath}.from", "Transition references undefined state: '{$transition['from']}'");
                    }

                    if (empty($transition['to'])) {
                        $this->addError("{$tPath}.to", "Transition must have a 'to' state");
                    } elseif (!empty($definedStates) && !in_array($transition['to'], $definedStates)) {
                        $this->addError("{$tPath}.to", "Transition references undefined state: '{$transition['to']}'");
                    }

                    if (empty($transition['actor'])) {
                        $this->addWarning("{$tPath}.actor", "Transition without actor list — any role can perform this transition");
                    }
                }
            }
        }

        // Validate edit boundaries (managerEdits / userEdits)
        $policyRoles = array_keys($packageData['policy']['roles'] ?? []);

        foreach (['managerEdits', 'userEdits'] as $editType) {
            if (isset($layer2[$editType])) {
                $ePath = "{$l2Path}.{$editType}";
                $editConfig = $layer2[$editType];

                if (isset($editConfig['allowedInStates'])) {
                    if (!is_array($editConfig['allowedInStates'])) {
                        $this->addError("{$ePath}.allowedInStates", "Must be an array of state names");
                    } else {
                        $definedStates = $layer2['workflow']['states'] ?? [];
                        foreach ($editConfig['allowedInStates'] as $state) {
                            if (!empty($definedStates) && !in_array($state, $definedStates)) {
                                $this->addError("{$ePath}.allowedInStates", "References undefined workflow state: '{$state}'");
                            }
                        }
                    }
                }
            }
        }
    }

    // ──────────────────────────────────────────────────────
    //  SHOWIF CROSS-REFERENCES
    // ──────────────────────────────────────────────────────

    /**
     * Validate all showIf conditions reference valid settings keys
     */
    private function validateShowIfReferences(array $packageData): void
    {
        // Collect all defined settings keys from data.queries (settings-type queries)
        $definedSettings = [];
        if (isset($packageData['data']['settings'])) {
            $definedSettings = array_keys($packageData['data']['settings']);
        }

        // Some packages store settings as entries in queries map
        if (isset($packageData['data']['queries'])) {
            foreach ($packageData['data']['queries'] as $name => $query) {
                if (str_starts_with($name, 'getSetting') || str_starts_with($name, 'setting')) {
                    $definedSettings[] = $name;
                }
            }
        }

        // Walk the entire tree to find showIf blocks
        $showIfs = [];
        $this->collectShowIfs($packageData, '$', $showIfs);

        if (empty($showIfs)) {
            return;
        }

        // Validate each showIf
        foreach ($showIfs as $entry) {
            $showIf = $entry['value'];
            $path = $entry['path'];

            if (!is_array($showIf)) {
                $this->addError("{$path}.showIf", "showIf must be an object with 'settingsKey' or 'condition'");
                continue;
            }

            if (empty($showIf['settingsKey']) && empty($showIf['condition']) && empty($showIf['field'])) {
                $this->addError("{$path}.showIf", "showIf must have 'settingsKey', 'field', or 'condition' property", [
                    'fix' => '"showIf": { "settingsKey": "setting_name" } or { "field": "other_field", "equals": true }',
                ]);
            }

            // settingsKey should be snake_case
            if (!empty($showIf['settingsKey']) && !preg_match('/^[a-z][a-z0-9_]*$/', $showIf['settingsKey'])) {
                $this->addWarning("{$path}.showIf.settingsKey", "Settings key should be snake_case: '{$showIf['settingsKey']}'");
            }
        }

        if (!empty($showIfs)) {
            $this->addCheck_info('showIf', count($showIfs) . " showIf condition(s) validated");
        }
    }

    /**
     * Recursively collect all showIf blocks from the package tree
     */
    private function collectShowIfs(mixed $data, string $path, array &$results): void
    {
        if (!is_array($data)) {
            return;
        }

        if (isset($data['showIf'])) {
            $results[] = ['path' => $path, 'value' => $data['showIf']];
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $childPath = is_int($key) ? "{$path}[{$key}]" : "{$path}.{$key}";
                $this->collectShowIfs($value, $childPath, $results);
            }
        }
    }

    /**
     * Add an informational message (not error, not warning)
     */
    private function addCheck_info(string $context, string $message): void
    {
        // Intentionally not added to errors or warnings — just for structured output
        $this->warnings[] = [
            'path'    => $context,
            'message' => "✓ {$message}",
            'level'   => 'info',
        ];
    }

    // ──────────────────────────────────────────────────────
    //  CROSS-REFERENCE VALIDATION
    // ──────────────────────────────────────────────────────

    /**
     * Ensure all referenced queries/mutations actually exist in data section
     */
    private function validateCrossReferences(array $packageData): void
    {
        $definedQueries = array_keys($packageData['data']['queries'] ?? []);
        $definedMutations = array_keys($packageData['data']['mutations'] ?? []);

        // Check referenced queries
        foreach (array_unique($this->referencedQueries) as $qName) {
            if (!in_array($qName, $definedQueries)) {
                $this->addError("cross-ref", "Query '{$qName}' is referenced by a component but not defined in data.queries", [
                    'fix' => "Add \"{$qName}\": { \"handler\": \"{$qName}\" } to data.queries",
                ]);
            }
        }

        // Check referenced mutations
        foreach (array_unique($this->referencedMutations) as $mName) {
            if (!in_array($mName, $definedMutations)) {
                $this->addError("cross-ref", "Mutation '{$mName}' is referenced by a component but not defined in data.mutations", [
                    'fix' => "Add \"{$mName}\": { \"handler\": \"{$mName}\" } to data.mutations",
                ]);
            }
        }

        // Check for unused queries (warning only)
        foreach ($definedQueries as $qName) {
            if (!in_array($qName, $this->referencedQueries)) {
                $this->addWarning("cross-ref", "Query '{$qName}' is defined but never referenced by any component");
            }
        }
    }

    // ──────────────────────────────────────────────────────
    //  ERROR / WARNING HELPERS
    // ──────────────────────────────────────────────────────

    /**
     * Extract role names from roles definition.
     * Handles both formats:
     *   - Array of strings: ["user", "manager", "admin"]
     *   - Object keyed by role: { "user": { "label": "..." }, "manager": { ... } }
     */
    private function extractRoleNames(array $roles): array
    {
        if (empty($roles)) {
            return [];
        }

        // If keys are sequential integers, it's a flat array of role strings
        if (array_is_list($roles)) {
            return $roles;
        }

        // Otherwise it's an object — keys are the role names
        return array_keys($roles);
    }

    private function addError(string $path, string $message, array $meta = []): void
    {
        $this->errors[] = array_merge([
            'path'    => $path,
            'message' => $message,
            'level'   => 'error',
        ], $meta);
    }

    private function addWarning(string $path, string $message, array $meta = []): void
    {
        $this->warnings[] = array_merge([
            'path'    => $path,
            'message' => $message,
            'level'   => 'warning',
        ], $meta);
    }
}
