<?php

namespace Hub\Package;

/**
 * Component Catalog — The Single Source of Truth
 *
 * Every component type in the package system, its rules, required/optional
 * fields, allowed values, visual tokens, and a working JSON example.
 *
 * Purpose:
 *   - Contributors: "What can I build? What fields does each type need?"
 *   - Tooling: Generates docs, powers scaffold CLI, validates JSON
 *   - Admins: "What component types exist? What can I customize?"
 *
 * Usage:
 *   ComponentCatalog::types()             → list all component types
 *   ComponentCatalog::get('table')        → full catalog entry for tables
 *   ComponentCatalog::example('table')    → working JSON example
 *   ComponentCatalog::rules('table')      → field rules only
 *   ComponentCatalog::fieldTypes('form')  → allowed field types for forms
 *   ComponentCatalog::toMarkdown()        → full reference doc
 *
 * @see ComponentSchema for validation enforcement
 * @see VisualConfig for visual token definitions
 * @see docs/VISUAL_TOKEN_SYSTEM.md for visual customization
 */
class ComponentCatalog
{
    /**
     * All available component types with metadata.
     *
     * @return array<string, array{name: string, icon: string, purpose: string, tags: string[]}>
     */
    public static function types(): array
    {
        return [
            'table'     => ['name' => 'Data Table',     'icon' => 'fas fa-table',            'purpose' => 'Paginated, sortable, searchable data grid with row/bulk actions and export', 'tags' => ['data', 'list', 'grid', 'crud']],
            'filters'   => ['name' => 'Filter Bar',     'icon' => 'fas fa-filter',           'purpose' => 'Search and filter controls that target a data table',                        'tags' => ['search', 'filter', 'query']],
            'dashboard' => ['name' => 'Dashboard',      'icon' => 'fas fa-chart-line',       'purpose' => 'KPI cards, charts, action queues, and quick links',                         'tags' => ['kpi', 'stats', 'charts', 'overview']],
            'form'      => ['name' => 'Form',           'icon' => 'fas fa-edit',             'purpose' => 'Create/edit forms with sections, validation, and grid layout',               'tags' => ['input', 'create', 'edit', 'crud']],
            'detail'    => ['name' => 'Detail View',    'icon' => 'fas fa-file-alt',         'purpose' => 'Single-record detail display with sectioned layout and actions',             'tags' => ['view', 'record', 'read']],
        ];
    }

    /**
     * Get the full catalog entry for a component type.
     *
     * @return array{
     *   name: string, icon: string, purpose: string,
     *   required: array, optional: array, fieldTypes: array,
     *   visualTokens: string[], example: array
     * }|null
     */
    public static function get(string $type): ?array
    {
        $catalog = self::catalog();
        return $catalog[$type] ?? null;
    }

    /**
     * Get the rules (required/optional fields) for a component type.
     */
    public static function rules(string $type): ?array
    {
        $entry = self::get($type);
        if (!$entry) return null;
        return [
            'required' => $entry['required'],
            'optional' => $entry['optional'],
        ];
    }

    /**
     * Get a working JSON example for a component type.
     */
    public static function example(string $type): ?array
    {
        $entry = self::get($type);
        return $entry['example'] ?? null;
    }

    /**
     * Get the example as formatted JSON.
     */
    public static function exampleJson(string $type): ?string
    {
        $example = self::example($type);
        if (!$example) return null;
        return json_encode($example, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Get allowed field/column types for a component type.
     *
     * @return string[]|null
     */
    public static function fieldTypes(string $type): ?array
    {
        $entry = self::get($type);
        return $entry['fieldTypes'] ?? null;
    }

    /**
     * Get relevant visual token names for a component type.
     *
     * @return string[]|null
     */
    public static function visualTokens(string $type): ?array
    {
        $entry = self::get($type);
        return $entry['visualTokens'] ?? null;
    }

    /**
     * Search the catalog by tag or keyword.
     *
     * @param string $query Search term
     * @return array<string, array> Matching catalog entries
     */
    public static function search(string $query): array
    {
        $query = strtolower($query);
        $results = [];
        foreach (self::types() as $type => $meta) {
            $haystack = strtolower($meta['name'] . ' ' . $meta['purpose'] . ' ' . implode(' ', $meta['tags']));
            if (str_contains($haystack, $query)) {
                $results[$type] = self::get($type);
            }
        }
        return $results;
    }

    // ─────────────────────────────────────────────────────
    //  CATALOG DATA
    // ─────────────────────────────────────────────────────

    /**
     * The full catalog — all types with their rules and examples.
     */
    private static function catalog(): array
    {
        return [
            'table'     => self::tableCatalog(),
            'filters'   => self::filtersCatalog(),
            'dashboard' => self::dashboardCatalog(),
            'form'      => self::formCatalog(),
            'detail'    => self::detailCatalog(),
        ];
    }

    // ─────────────────────────────────────────────────────
    //  TABLE
    // ─────────────────────────────────────────────────────

    private static function tableCatalog(): array
    {
        $meta = self::types()['table'];
        return array_merge($meta, [
            'required' => [
                'columns' => [
                    'type'        => 'array',
                    'description' => 'Column definitions (1-30 columns)',
                    'minItems'    => 1,
                    'maxItems'    => 30,
                    'itemFields'  => [
                        'key'   => ['required' => true,  'type' => 'string', 'description' => 'Database column name or computed field key'],
                        'label' => ['required' => true,  'type' => 'string', 'description' => 'Column header text (max 50 chars)'],
                        'type'  => ['required' => false, 'type' => 'string', 'description' => 'Display type', 'default' => 'text', 'allowed' => ['text', 'badge', 'date', 'datetime', 'currency', 'link', 'email', 'boolean', 'image', 'masked']],
                        'sortable'  => ['required' => false, 'type' => 'boolean', 'default' => false],
                        'searchable' => ['required' => false, 'type' => 'boolean', 'default' => false],
                        'hideMobile' => ['required' => false, 'type' => 'boolean', 'default' => false, 'description' => 'Hide on mobile breakpoint'],
                        'hideTablet' => ['required' => false, 'type' => 'boolean', 'default' => false],
                        'dataClass'  => ['required' => false, 'type' => 'string', 'allowed' => ['public', 'internal', 'confidential', 'regulated', 'secret'], 'default' => 'internal', 'description' => 'Data classification for masking'],
                        'badgeMap'   => ['required' => false, 'type' => 'object', 'description' => 'Maps values to badge variants (for badge type)'],
                        'width'      => ['required' => false, 'type' => 'string', 'description' => 'CSS width (e.g., "120px", "15%")'],
                    ],
                ],
            ],
            'optional' => [
                'id' => [
                    'type' => 'string', 'description' => 'CSS ID for targeting from filters (e.g., "pkg-table-students")',
                    'pattern' => '^[a-z][a-z0-9-]*$',
                ],
                'actions' => [
                    'type' => 'array', 'description' => 'Row action buttons (max 6)',
                    'maxItems' => 6,
                    'itemFields' => [
                        'label'   => ['required' => true,  'type' => 'string'],
                        'type'    => ['required' => true,  'type' => 'string', 'allowed' => ['route', 'mutation', 'link', 'modal']],
                        'icon'    => ['required' => false, 'type' => 'string', 'description' => 'FontAwesome class (e.g., "fas fa-eye")'],
                        'variant' => ['required' => false, 'type' => 'string', 'allowed' => ['primary', 'success', 'warning', 'danger', 'info', 'secondary', 'outline-primary', 'outline-danger']],
                        'to'      => ['required' => false, 'type' => 'string', 'description' => 'Route path with {id} placeholder'],
                        'mutation' => ['required' => false, 'type' => 'string', 'description' => 'Mutation name for mutation-type actions'],
                        'confirm' => ['required' => false, 'type' => 'string', 'description' => 'Confirmation message before executing'],
                        'access'  => ['required' => false, 'type' => 'array',  'description' => 'Roles that can see this action'],
                    ],
                ],
                'bulkActions' => [
                    'type' => 'array', 'description' => 'Bulk action buttons for selected rows (max 5)', 'maxItems' => 5,
                    'itemFields' => [
                        'label'    => ['required' => true, 'type' => 'string'],
                        'mutation' => ['required' => true, 'type' => 'string'],
                        'icon'     => ['required' => false, 'type' => 'string'],
                        'variant'  => ['required' => false, 'type' => 'string'],
                        'confirm'  => ['required' => false, 'type' => 'string'],
                    ],
                ],
                'pagination' => [
                    'type' => 'object', 'description' => 'Pagination config',
                    'fields' => [
                        'perPage'   => ['type' => 'integer', 'default' => 50, 'min' => 5, 'max' => 500],
                        'pageSizes' => ['type' => 'array', 'default' => [25, 50, 100]],
                    ],
                ],
                'emptyMessage' => ['type' => 'string', 'default' => 'No records found'],
                'exportable'   => ['type' => 'boolean', 'default' => false, 'description' => 'Show export button'],
                'visual'       => ['type' => 'object', 'description' => 'Visual config — see Visual Tokens section'],
            ],
            'fieldTypes' => ['text', 'badge', 'date', 'datetime', 'currency', 'link', 'email', 'boolean', 'image', 'masked'],
            'visualTokens' => [
                'table-row-height', 'table-cell-padding-x', 'table-cell-padding-y',
                'table-header-padding-x', 'table-header-padding-y',
                'table-font-size', 'table-header-font-size',
                'table-border-radius', 'table-border-color',
                'table-header-bg', 'table-header-color',
                'table-row-bg', 'table-row-hover', 'table-row-stripe', 'table-row-selected',
                'table-text-color', 'table-divider-color', 'table-shadow',
                'action-btn-size', 'action-btn-padding-x', 'action-btn-padding-y', 'action-btn-radius', 'action-gap',
                'badge-font-size', 'badge-padding-x', 'badge-padding-y', 'badge-radius',
                'pagination-gap', 'pagination-btn-size', 'pagination-padding',
            ],
            'example' => [
                'type' => 'table',
                'config' => [
                    'id' => 'pkg-table-employees',
                    'columns' => [
                        ['key' => 'name',       'label' => 'Name',       'sortable' => true, 'searchable' => true],
                        ['key' => 'email',      'label' => 'Email',      'type' => 'email', 'hideMobile' => true],
                        ['key' => 'department', 'label' => 'Department', 'type' => 'badge', 'badgeMap' => ['Technology' => 'primary', 'Admin' => 'info', 'Operations' => 'warning']],
                        ['key' => 'hire_date',  'label' => 'Hire Date',  'type' => 'date', 'sortable' => true, 'hideTablet' => true],
                        ['key' => 'status',     'label' => 'Status',     'type' => 'badge', 'badgeMap' => ['Active' => 'success', 'Inactive' => 'danger']],
                    ],
                    'actions' => [
                        ['label' => 'View',   'icon' => 'fas fa-eye',       'type' => 'route', 'variant' => 'outline-primary', 'to' => '/view?id={id}'],
                        ['label' => 'Edit',   'icon' => 'fas fa-edit',      'type' => 'route', 'variant' => 'outline-primary', 'to' => '/edit?id={id}', 'access' => ['admin', 'manager']],
                        ['label' => 'Delete', 'icon' => 'fas fa-trash-alt', 'type' => 'mutation', 'variant' => 'outline-danger', 'mutation' => 'deleteRecord', 'confirm' => 'Are you sure you want to delete this record?', 'access' => ['admin']],
                    ],
                    'bulkActions' => [
                        ['label' => 'Export Selected', 'icon' => 'fas fa-download', 'mutation' => 'exportSelected', 'variant' => 'primary'],
                    ],
                    'pagination' => ['perPage' => 50, 'pageSizes' => [25, 50, 100]],
                    'exportable' => true,
                    'visual' => [
                        'preset' => 'comfortable',
                    ],
                ],
                'dataQuery' => 'listEmployees',
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────
    //  FILTERS
    // ─────────────────────────────────────────────────────

    private static function filtersCatalog(): array
    {
        $meta = self::types()['filters'];
        return array_merge($meta, [
            'required' => [
                'filters' => [
                    'type'        => 'array',
                    'description' => 'Filter field definitions (1-10 filters)',
                    'minItems'    => 1,
                    'maxItems'    => 10,
                    'itemFields'  => [
                        'name' => ['required' => true,  'type' => 'string', 'description' => 'URL parameter name (e.g., "search", "grade", "status")'],
                        'type' => ['required' => true,  'type' => 'string', 'allowed' => ['search', 'select', 'date', 'date_range', 'checkbox', 'text']],
                        'label'       => ['required' => false, 'type' => 'string', 'description' => 'Label above the field (uppercased automatically)'],
                        'placeholder' => ['required' => false, 'type' => 'string'],
                        'debounce'    => ['required' => false, 'type' => 'integer', 'min' => 0, 'max' => 2000, 'description' => 'Debounce delay in ms (for search type)'],
                        'options'     => ['required' => false, 'type' => 'array', 'description' => 'Static options for select: [{value, label}]'],
                        'optionsQuery' => ['required' => false, 'type' => 'string', 'description' => 'Query name to load options dynamically'],
                        'allowAll'    => ['required' => false, 'type' => 'boolean', 'default' => true, 'description' => 'Show "All" option in select'],
                        'startParam'  => ['required' => false, 'type' => 'string', 'description' => 'Start param name (for date_range)'],
                        'endParam'    => ['required' => false, 'type' => 'string', 'description' => 'End param name (for date_range)'],
                    ],
                ],
            ],
            'optional' => [
                'targetTable' => [
                    'type' => 'string', 'description' => 'CSS ID of the table these filters control (must match the table\'s config.id)',
                ],
                'visual' => ['type' => 'object', 'description' => 'Visual config — see Visual Tokens section'],
            ],
            'fieldTypes' => ['search', 'select', 'date', 'date_range', 'checkbox', 'text'],
            'visualTokens' => [
                'filter-input-width', 'filter-select-width', 'filter-input-height',
                'filter-gap', 'filter-padding', 'filter-bg',
                'filter-border-radius', 'filter-border-color',
                'filter-label-size', 'filter-label-color', 'filter-label-weight',
            ],
            'example' => [
                'type' => 'filters',
                'config' => [
                    'targetTable' => 'pkg-table-employees',
                    'filters' => [
                        ['name' => 'search',     'type' => 'search', 'placeholder' => 'Search by name, email, or ID...', 'debounce' => 300],
                        ['name' => 'department', 'type' => 'select', 'label' => 'Department', 'optionsQuery' => 'getDepartments'],
                        ['name' => 'status',     'type' => 'select', 'label' => 'Status', 'options' => [
                            ['value' => 'Active',   'label' => 'Active'],
                            ['value' => 'Inactive', 'label' => 'Inactive'],
                        ]],
                        ['name' => 'hire_date',  'type' => 'date_range', 'label' => 'Hire Date', 'startParam' => 'hired_from', 'endParam' => 'hired_to'],
                    ],
                    'visual' => [
                        'preset' => 'comfortable',
                        'tokens' => [
                            'filter-input-width' => '280px',
                        ],
                    ],
                ],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────
    //  DASHBOARD
    // ─────────────────────────────────────────────────────

    private static function dashboardCatalog(): array
    {
        $meta = self::types()['dashboard'];
        return array_merge($meta, [
            'required' => [
                'cards' => [
                    'type'        => 'array',
                    'description' => 'KPI stat cards (1-12 cards)',
                    'minItems'    => 1,
                    'maxItems'    => 12,
                    'itemFields'  => [
                        'title'   => ['required' => true,  'type' => 'string', 'description' => 'Card label (max 50 chars)'],
                        'dataKey' => ['required' => true,  'type' => 'string', 'description' => 'Key in query result data to display as the value'],
                        'icon'    => ['required' => false, 'type' => 'string', 'description' => 'FontAwesome icon class'],
                        'color'   => ['required' => false, 'type' => 'string', 'allowed' => ['primary', 'success', 'warning', 'danger', 'info', 'secondary', 'dark']],
                        'format'  => ['required' => false, 'type' => 'string', 'allowed' => ['number', 'currency', 'percentage', 'compact', 'text'], 'default' => 'number'],
                        'link'    => ['required' => false, 'type' => 'string', 'description' => 'Route when card is clicked'],
                        'subtitle' => ['required' => false, 'type' => 'string', 'description' => 'Small text below the value'],
                        'trend'   => ['required' => false, 'type' => 'object', 'description' => 'Trend indicator', 'fields' => [
                            'dataKey'   => ['type' => 'string', 'description' => 'Key for percentage change'],
                            'direction' => ['type' => 'string', 'allowed' => ['up', 'down']],
                        ]],
                    ],
                ],
            ],
            'optional' => [
                'columns' => [
                    'type' => 'integer', 'description' => 'Number of card columns (1-6)', 'default' => 4, 'min' => 1, 'max' => 6,
                ],
                'charts' => [
                    'type' => 'array', 'description' => 'Chart.js chart panels (max 6)',
                    'maxItems' => 6,
                    'itemFields' => [
                        'chartType' => ['required' => true, 'type' => 'string', 'allowed' => ['bar', 'line', 'pie', 'doughnut']],
                        'title'     => ['required' => false, 'type' => 'string'],
                        'dataKey'   => ['required' => false, 'type' => 'string', 'description' => 'Key in query data for chart datasets'],
                        'height'    => ['required' => false, 'type' => 'string', 'default' => '300px'],
                        'labels'    => ['required' => false, 'type' => 'array', 'description' => 'Static labels (or pull from data)'],
                    ],
                ],
                'chartColumns' => ['type' => 'integer', 'default' => 2, 'min' => 1, 'max' => 3],
                'queues' => [
                    'type' => 'array', 'description' => 'Action queue panels — pending items needing attention (max 4)',
                    'maxItems' => 4,
                    'itemFields' => [
                        'title'   => ['required' => true, 'type' => 'string'],
                        'dataKey' => ['required' => true, 'type' => 'string'],
                        'icon'    => ['required' => false, 'type' => 'string'],
                        'link'    => ['required' => false, 'type' => 'string'],
                    ],
                ],
                'quickLinks' => [
                    'type' => 'array', 'description' => 'Quick action shortcut buttons (max 12)',
                    'maxItems' => 12,
                    'itemFields' => [
                        'label' => ['required' => true, 'type' => 'string'],
                        'icon'  => ['required' => true, 'type' => 'string'],
                        'url'   => ['required' => true, 'type' => 'string'],
                        'description' => ['required' => false, 'type' => 'string'],
                    ],
                ],
                'visual' => ['type' => 'object', 'description' => 'Visual config — see Visual Tokens section'],
            ],
            'fieldTypes' => ['bar', 'line', 'pie', 'doughnut'],
            'visualTokens' => [
                'card-columns', 'card-gap', 'card-padding', 'card-radius',
                'card-shadow', 'card-border', 'card-icon-size', 'card-value-size',
            ],
            'example' => [
                'type' => 'dashboard',
                'config' => [
                    'columns' => 4,
                    'cards' => [
                        ['title' => 'Total Records',     'dataKey' => 'total',    'icon' => 'fas fa-database',   'color' => 'primary'],
                        ['title' => 'Active',            'dataKey' => 'active',   'icon' => 'fas fa-check-circle', 'color' => 'success'],
                        ['title' => 'Pending Review',    'dataKey' => 'pending',  'icon' => 'fas fa-clock',      'color' => 'warning', 'link' => '/pending'],
                        ['title' => 'Issues',            'dataKey' => 'issues',   'icon' => 'fas fa-exclamation-triangle', 'color' => 'danger'],
                    ],
                    'charts' => [
                        [
                            'chartType' => 'bar',
                            'title'     => 'Records by Month',
                            'dataKey'   => 'monthlyData',
                            'height'    => '300px',
                        ],
                        [
                            'chartType' => 'doughnut',
                            'title'     => 'Status Breakdown',
                            'dataKey'   => 'statusBreakdown',
                        ],
                    ],
                    'quickLinks' => [
                        ['label' => 'Add New',    'icon' => 'fas fa-plus',     'url' => '/create', 'description' => 'Create a new record'],
                        ['label' => 'Export',     'icon' => 'fas fa-download', 'url' => '/export', 'description' => 'Export data to Excel'],
                        ['label' => 'Settings',   'icon' => 'fas fa-cog',      'url' => '/settings'],
                    ],
                    'visual' => [
                        'preset' => 'comfortable',
                    ],
                ],
                'dataQuery' => 'getDashboardStats',
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────
    //  FORM
    // ─────────────────────────────────────────────────────

    private static function formCatalog(): array
    {
        $meta = self::types()['form'];
        return array_merge($meta, [
            'required' => [
                'mutation' => [
                    'type'        => 'string',
                    'description' => 'Mutation name to call on form submit (must be defined in package data.mutations)',
                ],
                '_oneOf' => [
                    'description' => 'Provide EITHER "sections" (multi-section form) OR "fields" (flat form), not both.',
                    'sections' => [
                        'type' => 'array', 'description' => 'Form sections — each section is a card with a title, optional grid, and fields',
                        'maxItems' => 20,
                        'itemFields' => [
                            'title'       => ['required' => true,  'type' => 'string', 'description' => 'Section heading (max 100 chars)'],
                            'fields'      => ['required' => true,  'type' => 'array',  'description' => 'Fields in this section'],
                            'description' => ['required' => false, 'type' => 'string', 'description' => 'Help text below heading'],
                            'grid'        => ['required' => false, 'type' => 'integer', 'description' => 'Grid columns (1-4)', 'min' => 1, 'max' => 4, 'default' => 1],
                            'collapsible' => ['required' => false, 'type' => 'boolean', 'default' => false],
                            'collapsed'   => ['required' => false, 'type' => 'boolean', 'default' => false],
                        ],
                    ],
                    'fields' => [
                        'type' => 'array', 'description' => 'Flat field list (max 100 fields)',
                        'maxItems' => 100,
                    ],
                ],
            ],
            'optional' => [
                'layout' => [
                    'type' => 'string', 'allowed' => ['vertical', 'horizontal', 'inline'], 'default' => 'vertical',
                ],
                'submitLabel' => ['type' => 'string', 'default' => 'Save', 'maxLength' => 30],
                'cancelUrl'   => ['type' => 'string', 'description' => 'Route to navigate on cancel'],
                'visual'      => ['type' => 'object', 'description' => 'Visual config — see Visual Tokens section'],
            ],
            'fieldTypes' => ['text', 'textarea', 'number', 'currency', 'email', 'date', 'datetime', 'select', 'radio', 'checkbox', 'file', 'password', 'url', 'tel', 'hidden'],
            'fieldSchema' => [
                'key'          => ['required' => true,  'type' => 'string', 'description' => 'Field name (maps to database column or form data key)'],
                'type'         => ['required' => true,  'type' => 'string', 'description' => 'Input type — see fieldTypes list'],
                'label'        => ['required' => true,  'type' => 'string', 'description' => 'Field label text'],
                'required'     => ['required' => false, 'type' => 'boolean', 'default' => false],
                'helpText'     => ['required' => false, 'type' => 'string', 'description' => 'Help text below the input'],
                'placeholder'  => ['required' => false, 'type' => 'string'],
                'defaultValue' => ['required' => false, 'type' => 'mixed', 'description' => 'Default value for create mode'],
                'colSpan'      => ['required' => false, 'type' => 'integer', 'description' => 'Grid columns to span (1-4)', 'min' => 1, 'max' => 4],
                'options'      => ['required' => false, 'type' => 'array', 'description' => 'For select/radio: [{value, label}]'],
                'optionsQuery' => ['required' => false, 'type' => 'string', 'description' => 'Query name to load options dynamically'],
                'showIf'       => ['required' => false, 'type' => 'object', 'description' => 'Conditional visibility: {field, operator, value}'],
                'min'          => ['required' => false, 'type' => 'number', 'description' => 'Minimum value (for number/currency)'],
                'max'          => ['required' => false, 'type' => 'number', 'description' => 'Maximum value'],
                'maxLength'    => ['required' => false, 'type' => 'integer', 'description' => 'Max character length'],
                'accept'       => ['required' => false, 'type' => 'string', 'description' => 'Accepted file types (for file type)'],
            ],
            'visualTokens' => [
                'form-label-size', 'form-label-weight', 'form-input-height',
                'form-input-radius', 'form-section-gap', 'form-field-gap', 'form-grid-gap',
            ],
            'example' => [
                'type' => 'form',
                'config' => [
                    'mutation'    => 'createEmployee',
                    'submitLabel' => 'Create Employee',
                    'cancelUrl'   => '/',
                    'sections' => [
                        [
                            'title'       => 'Personal Information',
                            'description' => 'Basic employee details',
                            'grid'        => 2,
                            'fields' => [
                                ['key' => 'first_name',  'type' => 'text',  'label' => 'First Name',  'required' => true, 'colSpan' => 1],
                                ['key' => 'last_name',   'type' => 'text',  'label' => 'Last Name',   'required' => true, 'colSpan' => 1],
                                ['key' => 'email',       'type' => 'email', 'label' => 'Email',       'required' => true, 'colSpan' => 2],
                                ['key' => 'phone',       'type' => 'tel',   'label' => 'Phone',       'placeholder' => '(555) 123-4567'],
                                ['key' => 'hire_date',   'type' => 'date',  'label' => 'Hire Date',   'required' => true],
                            ],
                        ],
                        [
                            'title' => 'Assignment',
                            'grid'  => 2,
                            'fields' => [
                                ['key' => 'department', 'type' => 'select', 'label' => 'Department', 'required' => true, 'optionsQuery' => 'getDepartments'],
                                ['key' => 'position',   'type' => 'text',   'label' => 'Position'],
                                ['key' => 'salary',     'type' => 'currency', 'label' => 'Salary', 'min' => 0, 'showIf' => ['field' => 'department', 'operator' => '!=', 'value' => '']],
                                ['key' => 'notes',      'type' => 'textarea', 'label' => 'Notes', 'colSpan' => 2, 'helpText' => 'Any additional information'],
                            ],
                        ],
                    ],
                    'visual' => [
                        'preset' => 'comfortable',
                    ],
                ],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────
    //  DETAIL
    // ─────────────────────────────────────────────────────

    private static function detailCatalog(): array
    {
        $meta = self::types()['detail'];
        return array_merge($meta, [
            'required' => [
                '_note' => 'Detail views have no strictly required fields — but at minimum you need sections with fields to display anything useful.',
            ],
            'optional' => [
                'sections' => [
                    'type' => 'array', 'description' => 'Display sections (max 20)',
                    'maxItems' => 20,
                    'itemFields' => [
                        'title'   => ['required' => true,  'type' => 'string', 'description' => 'Section heading text'],
                        'fields'  => ['required' => true,  'type' => 'array', 'description' => 'Fields to display in this section'],
                        'columns' => ['required' => false, 'type' => 'integer', 'description' => 'Grid columns (1-4)', 'default' => 2],
                    ],
                    'fieldItemFields' => [
                        'key'   => ['required' => true,  'type' => 'string', 'description' => 'Data key to display'],
                        'label' => ['required' => true,  'type' => 'string', 'description' => 'Field label (uppercased)'],
                        'type'  => ['required' => false, 'type' => 'string', 'allowed' => ['text', 'date', 'datetime', 'email', 'link', 'badge', 'boolean', 'image', 'longtext', 'list', 'currency', 'masked'], 'default' => 'text'],
                        'dataClass' => ['required' => false, 'type' => 'string', 'allowed' => ['public', 'internal', 'confidential', 'regulated', 'secret']],
                    ],
                ],
                'actions' => [
                    'type' => 'array', 'description' => 'Header action buttons (edit, delete, etc.)', 'maxItems' => 6,
                    'itemFields' => [
                        'label'   => ['required' => true,  'type' => 'string'],
                        'type'    => ['required' => true,  'type' => 'string', 'allowed' => ['route', 'mutation', 'link']],
                        'icon'    => ['required' => false, 'type' => 'string'],
                        'variant' => ['required' => false, 'type' => 'string'],
                        'to'      => ['required' => false, 'type' => 'string'],
                        'mutation' => ['required' => false, 'type' => 'string'],
                        'confirm' => ['required' => false, 'type' => 'string'],
                        'access'  => ['required' => false, 'type' => 'array'],
                    ],
                ],
                'backUrl' => ['type' => 'string', 'description' => 'Route for "Back" button'],
                'visual'  => ['type' => 'object', 'description' => 'Visual config — see Visual Tokens section'],
            ],
            'fieldTypes' => ['text', 'date', 'datetime', 'email', 'link', 'badge', 'boolean', 'image', 'longtext', 'list', 'currency', 'masked'],
            'visualTokens' => [
                'table-border-color', 'table-border-radius', 'table-text-color',
            ],
            'example' => [
                'type' => 'detail',
                'config' => [
                    'backUrl' => '/',
                    'actions' => [
                        ['label' => 'Edit',   'icon' => 'fas fa-edit',      'type' => 'route', 'variant' => 'primary', 'to' => '/edit?id={id}', 'access' => ['admin', 'manager']],
                        ['label' => 'Delete', 'icon' => 'fas fa-trash-alt', 'type' => 'mutation', 'variant' => 'danger', 'mutation' => 'deleteRecord', 'confirm' => 'Are you sure?', 'access' => ['admin']],
                    ],
                    'sections' => [
                        [
                            'title'   => 'Personal Information',
                            'columns' => 2,
                            'fields' => [
                                ['key' => 'name',       'label' => 'Full Name'],
                                ['key' => 'email',      'label' => 'Email',      'type' => 'email'],
                                ['key' => 'phone',      'label' => 'Phone'],
                                ['key' => 'hire_date',  'label' => 'Hire Date',  'type' => 'date'],
                                ['key' => 'status',     'label' => 'Status',     'type' => 'badge'],
                            ],
                        ],
                        [
                            'title'   => 'Assignment Details',
                            'columns' => 2,
                            'fields' => [
                                ['key' => 'department', 'label' => 'Department', 'type' => 'badge'],
                                ['key' => 'position',   'label' => 'Position'],
                                ['key' => 'salary',     'label' => 'Salary',    'type' => 'currency', 'dataClass' => 'confidential'],
                                ['key' => 'notes',      'label' => 'Notes',     'type' => 'longtext'],
                            ],
                        ],
                    ],
                    'visual' => [
                        'preset' => 'comfortable',
                    ],
                ],
                'dataQuery' => 'getEmployee',
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────
    //  REFERENCE GENERATION
    // ─────────────────────────────────────────────────────

    /**
     * Generate a complete Markdown reference of all component types.
     *
     * Useful for:
     *   - CLI: `php artisan catalog:reference > docs/COMPONENT_CATALOG.md`
     *   - API: Serve to contributors in a dashboard
     *   - CI: Auto-generate updated docs on commit
     */
    public static function toMarkdown(): string
    {
        $md = "# Component Catalog Reference\n\n";
        $md .= "> Auto-generated from `ComponentCatalog::toMarkdown()` — do not edit manually.\n\n";
        $md .= "Every component type available in the Hub package system, its rules, and a working JSON example.\n\n";

        // Table of contents
        $md .= "## Component Types\n\n";
        $md .= "| Type | Name | Purpose |\n";
        $md .= "|------|------|---------|\n";
        foreach (self::types() as $type => $meta) {
            $md .= "| `{$type}` | {$meta['name']} | {$meta['purpose']} |\n";
        }
        $md .= "\n---\n\n";

        // Each type
        foreach (self::catalog() as $type => $entry) {
            $md .= "## `{$type}` — {$entry['name']}\n\n";
            $md .= "**Purpose:** {$entry['purpose']}\n\n";
            $md .= "**Tags:** " . implode(', ', $entry['tags']) . "\n\n";

            // Required fields
            if (!empty($entry['required'])) {
                $md .= "### Required Fields\n\n";
                foreach ($entry['required'] as $field => $def) {
                    if ($field === '_note') {
                        $md .= "> {$def}\n\n";
                        continue;
                    }
                    if ($field === '_oneOf') {
                        $md .= "> **Note:** {$def['description']}\n\n";
                        foreach ($def as $k => $v) {
                            if ($k === 'description') continue;
                            $md .= "**`{$k}`** — {$v['description']}\n\n";
                        }
                        continue;
                    }
                    $md .= "**`{$field}`** ({$def['type']}) — {$def['description']}\n\n";
                    if (isset($def['itemFields'])) {
                        $md .= "| Property | Required | Type | Description |\n";
                        $md .= "|----------|----------|------|-------------|\n";
                        foreach ($def['itemFields'] as $prop => $propDef) {
                            $req = ($propDef['required'] ?? false) ? '**Yes**' : 'No';
                            $desc = $propDef['description'] ?? '';
                            if (isset($propDef['allowed'])) {
                                $desc .= ' Allowed: `' . implode('`, `', $propDef['allowed']) . '`';
                            }
                            if (isset($propDef['default'])) {
                                $desc .= " (default: `{$propDef['default']}`)";
                            }
                            $md .= "| `{$prop}` | {$req} | {$propDef['type']} | {$desc} |\n";
                        }
                        $md .= "\n";
                    }
                }
            }

            // Optional fields
            if (!empty($entry['optional'])) {
                $md .= "### Optional Fields\n\n";
                foreach ($entry['optional'] as $field => $def) {
                    $desc = $def['description'] ?? '';
                    if (isset($def['default'])) {
                        $desc .= " (default: `{$def['default']}`)";
                    }
                    $md .= "- **`{$field}`** ({$def['type']}) — {$desc}\n";
                }
                $md .= "\n";
            }

            // Field types
            if (!empty($entry['fieldTypes'])) {
                $md .= "### Allowed Types\n\n";
                $md .= '`' . implode('`, `', $entry['fieldTypes']) . "`\n\n";
            }

            // Field schema (for forms)
            if (!empty($entry['fieldSchema'])) {
                $md .= "### Field Schema\n\n";
                $md .= "Every field (in `sections[].fields` or `fields[]`) accepts:\n\n";
                $md .= "| Property | Required | Type | Description |\n";
                $md .= "|----------|----------|------|-------------|\n";
                foreach ($entry['fieldSchema'] as $prop => $propDef) {
                    $req = ($propDef['required'] ?? false) ? '**Yes**' : 'No';
                    $desc = $propDef['description'] ?? '';
                    $md .= "| `{$prop}` | {$req} | {$propDef['type']} | {$desc} |\n";
                }
                $md .= "\n";
            }

            // Visual tokens
            if (!empty($entry['visualTokens'])) {
                $md .= "### Visual Tokens\n\n";
                $md .= "Relevant `--v-*` tokens for this component:\n\n";
                $md .= '`' . implode('`, `', $entry['visualTokens']) . "`\n\n";
            }

            // Example
            if (!empty($entry['example'])) {
                $md .= "### Example\n\n";
                $md .= "```json\n";
                $md .= json_encode($entry['example'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
                $md .= "```\n\n";
            }

            $md .= "---\n\n";
        }

        // Visual presets reference
        $md .= "## Visual Presets\n\n";
        $md .= "Apply a preset to any component via `\"visual\": { \"preset\": \"<name>\" }`:\n\n";
        $md .= "| Preset | Purpose |\n";
        $md .= "|--------|---------|\n";
        $presets = VisualPresets::all();
        foreach ($presets as $name => $preset) {
            $desc = $preset['description'] ?? $name;
            $md .= "| `{$name}` | {$desc} |\n";
        }
        $md .= "\n";

        return $md;
    }
}
