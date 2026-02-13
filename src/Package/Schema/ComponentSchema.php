<?php

namespace Hub\Package\Schema;

/**
 * Component Schema — "Stack" Type Definitions
 *
 * Defines the RULES and BOUNDARIES for every component type in the package system.
 * Each component type ("stack") has:
 *   - Required and optional properties
 *   - Allowed values/enums for each property
 *   - Child schemas (fields, cards, columns, etc.)
 *   - Validation rules and constraints
 *
 * Inspired by ParentSquare's "Stacks" model: each stack type has its own set of rules,
 * and creators build within those constraints. The system validates and enforces.
 *
 * @see CONTRIBUTING.md for package creation documentation
 * @see PACKAGE_ARCHITECTURE_SPEC.md for architecture overview
 */
class ComponentSchema
{
    /**
     * Get the complete schema for a component type
     *
     * @param string $type Component type (dashboard, table, form, detail, filters)
     * @return array Schema definition with rules
     * @throws \InvalidArgumentException if type unknown
     */
    public static function getSchema(string $type): array
    {
        $schemas = self::getAllSchemas();
        if (!isset($schemas[$type])) {
            throw new \InvalidArgumentException(
                "Unknown component type: '{$type}'. Valid types: " . implode(', ', array_keys($schemas))
            );
        }
        return $schemas[$type];
    }

    /**
     * Get all component type schemas
     *
     * @return array<string, array> All stack type definitions
     */
    public static function getAllSchemas(): array
    {
        return [
            'dashboard' => self::dashboardSchema(),
            'cards'     => self::dashboardSchema(), // alias
            'table'     => self::tableSchema(),
            'form'      => self::formSchema(),
            'detail'    => self::detailSchema(),
            'filters'   => self::filtersSchema(),
        ];
    }

    /**
     * Get the schema for the top-level package.json structure
     */
    public static function packageSchema(): array
    {
        return [
            'type'        => 'object',
            'description' => 'Complete package definition (schema v3.0.0)',
            'required'    => ['schemaVersion', 'package', 'database', 'presentation', 'data'],
            'properties'  => [
                'schemaVersion' => [
                    'type'        => 'string',
                    'description' => 'Package schema version',
                    'enum'        => ['3.0.0'],
                    'default'     => '3.0.0',
                ],
                'package' => [
                    'type'        => 'object',
                    'description' => 'Package metadata',
                    'required'    => ['id', 'display_name', 'version', 'category'],
                    'properties'  => [
                        'id' => [
                            'type'        => 'string',
                            'description' => 'Unique package identifier (category.name format)',
                            'pattern'     => '^[a-z]+\.[a-z0-9-]+$',
                            'examples'    => ['district.student-directory', 'operations.vehicle-maintenance'],
                        ],
                        'display_name' => [
                            'type'        => 'string',
                            'description' => 'Human-readable package name',
                            'minLength'   => 3,
                            'maxLength'   => 100,
                        ],
                        'description' => [
                            'type'        => 'string',
                            'description' => 'Brief description of the package',
                            'maxLength'   => 500,
                        ],
                        'version' => [
                            'type'        => 'string',
                            'description' => 'Semantic version (major.minor.patch)',
                            'pattern'     => '^\d+\.\d+\.\d+$',
                        ],
                        'author' => [
                            'type'        => 'string',
                            'description' => 'Package author or organization',
                        ],
                        'icon' => [
                            'type'        => 'string',
                            'description' => 'Icon identifier (lucide-* or bi-*)',
                            'pattern'     => '^(lucide-|bi-)[a-z0-9-]+$',
                        ],
                        'category' => [
                            'type'        => 'string',
                            'description' => 'Package category determines filesystem location',
                            'enum'        => ['district', 'operations', 'local', 'admin'],
                        ],
                        'base_url' => [
                            'type'        => 'string',
                            'description' => 'Auto-generated from package ID (/p/{id})',
                            'pattern'     => '^/p/[a-z0-9.-]+$',
                        ],
                        'tags' => [
                            'type'        => 'array',
                            'description' => 'Searchable tags for package discovery',
                            'items'       => ['type' => 'string'],
                            'maxItems'    => 20,
                        ],
                    ],
                ],
                'database' => [
                    'type'        => 'object',
                    'description' => 'Database connection and table config',
                    'required'    => ['connection', 'primaryTable'],
                    'properties'  => [
                        'connection' => [
                            'type'        => 'string',
                            'description' => 'MySQL database name',
                            'pattern'     => '^[a-z][a-z0-9_]+$',
                        ],
                        'primaryTable' => [
                            'type'        => 'string',
                            'description' => 'Main data table name',
                            'pattern'     => '^[a-z][a-z0-9_]+$',
                        ],
                        'auditTable' => [
                            'type'        => 'string',
                            'description' => 'Audit log table name (optional)',
                            'pattern'     => '^[a-z][a-z0-9_]+$',
                        ],
                    ],
                ],
                'presentation' => [
                    'type'        => 'object',
                    'description' => 'Pages and their component layouts',
                    'required'    => ['pages'],
                    'properties'  => [
                        'pages' => [
                            'type'        => 'object',
                            'description' => 'Map of page ID → page definition. Must include "index".',
                            'minProperties' => 1,
                            'additionalProperties' => self::pageSchema(),
                        ],
                    ],
                ],
                'data' => [
                    'type'        => 'object',
                    'description' => 'Query and mutation definitions',
                    'properties'  => [
                        'queries' => [
                            'type'        => 'object',
                            'description' => 'Named query definitions',
                            'additionalProperties' => self::querySchema(),
                        ],
                        'mutations' => [
                            'type'        => 'object',
                            'description' => 'Named mutation definitions',
                            'additionalProperties' => self::mutationSchema(),
                        ],
                    ],
                ],
                'policy' => [
                    'type'        => 'object',
                    'description' => 'Access control and role definitions',
                    'properties'  => [
                        'roles' => [
                            'type'        => 'array',
                            'description' => 'Package-specific role hierarchy (lowest → highest)',
                            'items'       => ['type' => 'string'],
                            'default'     => ['viewer', 'user', 'manager', 'admin'],
                        ],
                        'defaultRole' => [
                            'type'        => 'string',
                            'description' => 'Role assigned to authenticated users by default',
                        ],
                        'globalRoleMapping' => [
                            'type'        => 'object',
                            'description' => 'Maps system roles to package roles',
                            'properties'  => [
                                'super_admin' => ['type' => 'string'],
                                'admin'       => ['type' => 'string'],
                                'manager'     => ['type' => 'string'],
                                'user'        => ['type' => 'string'],
                                'viewer'      => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
                'access' => [
                    'type'        => 'object',
                    'description' => 'Layer 2 workflow (approval chains, edit boundaries)',
                    'properties'  => [
                        'workflow' => self::workflowSchema(),
                        'editBoundaries' => [
                            'type'        => 'object',
                            'description' => 'Which statuses allow editing, keyed by role',
                            'additionalProperties' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    // ──────────────────────────────────────────────────────
    //  PAGE SCHEMA
    // ──────────────────────────────────────────────────────

    /**
     * Schema for a page definition inside presentation.pages
     */
    public static function pageSchema(): array
    {
        return [
            'type'        => 'object',
            'description' => 'A page within the package (e.g., index, add, detail)',
            'required'    => ['title', 'route', 'components'],
            'properties'  => [
                'title' => [
                    'type'        => 'string',
                    'description' => 'Page title displayed in header',
                    'minLength'   => 1,
                    'maxLength'   => 100,
                ],
                'route' => [
                    'type'        => 'string',
                    'description' => 'URL path relative to package base_url. Use {param} for dynamic segments.',
                    'pattern'     => '^/[a-z0-9{}/._-]*$',
                    'examples'    => ['/', '/add', '/vehicle/{id}', '/fuel/add'],
                ],
                'layout' => [
                    'type'        => 'string',
                    'description' => 'Page layout mode',
                    'enum'        => ['full', 'standard', 'sidebar', 'narrow', 'split'],
                    'default'     => 'full',
                ],
                'requiredRole' => [
                    'type'        => 'string',
                    'description' => 'Minimum package role required to access this page',
                ],
                'components' => [
                    'type'        => 'array',
                    'description' => 'Ordered list of components rendered on this page',
                    'items'       => self::componentReferenceSchema(),
                    'minItems'    => 1,
                    'maxItems'    => 20,
                ],
            ],
        ];
    }

    /**
     * Schema for a component reference inside a page's components array
     */
    public static function componentReferenceSchema(): array
    {
        return [
            'type'        => 'object',
            'description' => 'A component placed on a page',
            'required'    => ['type', 'config'],
            'properties'  => [
                'type' => [
                    'type'        => 'string',
                    'description' => 'Component type (stack type)',
                    'enum'        => ['dashboard', 'cards', 'table', 'form', 'detail', 'filters'],
                ],
                'config' => [
                    'type'        => 'object',
                    'description' => 'Component-specific configuration (see stack type schemas)',
                ],
                'dataQuery' => [
                    'type'        => 'string',
                    'description' => 'Query name that provides data for this component',
                ],
                'requiredRole' => [
                    'type'        => 'string',
                    'description' => 'Minimum role to see this component',
                ],
            ],
        ];
    }

    // ──────────────────────────────────────────────────────
    //  DASHBOARD STACK
    // ──────────────────────────────────────────────────────

    /**
     * Dashboard component stack schema
     *
     * Renders KPI cards, charts, action queues, and quick links.
     * Cards show a single metric with icon and optional trend.
     */
    public static function dashboardSchema(): array
    {
        return [
            'type'        => 'dashboard',
            'description' => 'KPI cards, charts, action queues, and quick link panels',
            'icon'        => 'lucide-layout-dashboard',
            'configSchema' => [
                'type'       => 'object',
                'required'   => ['cards'],
                'properties' => [
                    'columns' => [
                        'type'        => 'integer',
                        'description' => 'Number of card columns (1-6)',
                        'minimum'     => 1,
                        'maximum'     => 6,
                        'default'     => 4,
                    ],
                    'cards' => [
                        'type'        => 'array',
                        'description' => 'KPI stat cards',
                        'items'       => self::cardItemSchema(),
                        'minItems'    => 1,
                        'maxItems'    => 12,
                    ],
                    'charts' => [
                        'type'        => 'array',
                        'description' => 'Chart.js chart panels',
                        'items'       => self::chartItemSchema(),
                        'maxItems'    => 6,
                    ],
                    'chartColumns' => [
                        'type'    => 'integer',
                        'minimum' => 1,
                        'maximum' => 3,
                        'default' => 2,
                    ],
                    'queues' => [
                        'type'        => 'array',
                        'description' => 'Action queue panels (pending items needing attention)',
                        'items'       => self::queueItemSchema(),
                        'maxItems'    => 4,
                    ],
                    'quickLinks' => [
                        'type'        => 'array',
                        'description' => 'Quick action buttons',
                        'items'       => self::quickLinkSchema(),
                        'maxItems'    => 12,
                    ],
                ],
            ],
        ];
    }

    private static function cardItemSchema(): array
    {
        return [
            'type'       => 'object',
            'required'   => ['title', 'dataKey'],
            'properties' => [
                'title' => [
                    'type' => 'string', 'description' => 'Card label', 'maxLength' => 50,
                ],
                'dataKey' => [
                    'type' => 'string', 'description' => 'Key in query result to pull value from',
                ],
                'icon' => [
                    'type' => 'string', 'description' => 'Icon identifier (lucide-* or bi-*)',
                    'pattern' => '^(lucide-|bi-)[a-z0-9-]+$',
                ],
                'color' => [
                    'type' => 'string', 'description' => 'Card color theme',
                    'enum' => ['primary', 'success', 'warning', 'danger', 'info', 'secondary', 'dark'],
                ],
                'format' => [
                    'type' => 'string', 'description' => 'Value display format',
                    'enum' => ['number', 'currency', 'percentage', 'compact', 'text'],
                    'default' => 'number',
                ],
                'link' => [
                    'type' => 'string', 'description' => 'Route when card is clicked',
                ],
                'subtitle' => [
                    'type' => 'string', 'description' => 'Small text below value',
                ],
                'trend' => [
                    'type'       => 'object',
                    'description' => 'Trend indicator arrow',
                    'properties' => [
                        'direction' => ['type' => 'string', 'enum' => ['up', 'down']],
                        'value'     => ['type' => 'string'],
                        'positive'  => ['type' => 'boolean'],
                    ],
                ],
            ],
        ];
    }

    private static function chartItemSchema(): array
    {
        return [
            'type'       => 'object',
            'required'   => ['chartType'],
            'properties' => [
                'id'        => ['type' => 'string'],
                'chartType' => [
                    'type' => 'string', 'enum' => ['bar', 'line', 'pie', 'doughnut', 'radar', 'polarArea'],
                ],
                'title'   => ['type' => 'string', 'maxLength' => 100],
                'height'  => ['type' => 'integer', 'minimum' => 100, 'maximum' => 800, 'default' => 300],
                'dataKey' => ['type' => 'string', 'description' => 'Key in query result for chart data'],
                'labels'  => ['type' => 'array', 'items' => ['type' => 'string']],
                'datasets' => ['type' => 'array'],
                'options' => ['type' => 'object', 'description' => 'Chart.js options override'],
            ],
        ];
    }

    private static function queueItemSchema(): array
    {
        return [
            'type'       => 'object',
            'required'   => ['title'],
            'properties' => [
                'title'        => ['type' => 'string'],
                'emptyMessage' => ['type' => 'string', 'default' => 'No pending items'],
                'items' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'label'   => ['type' => 'string'],
                            'dataKey' => ['type' => 'string'],
                            'count'   => ['type' => 'integer'],
                            'link'    => ['type' => 'string'],
                            'variant' => ['type' => 'string', 'enum' => ['primary', 'success', 'warning', 'danger', 'info']],
                            'icon'    => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private static function quickLinkSchema(): array
    {
        return [
            'type'       => 'object',
            'required'   => ['label', 'url'],
            'properties' => [
                'label'       => ['type' => 'string'],
                'url'         => ['type' => 'string'],
                'icon'        => ['type' => 'string'],
                'variant'     => ['type' => 'string', 'default' => 'outline-primary'],
                'description' => ['type' => 'string'],
            ],
        ];
    }

    // ──────────────────────────────────────────────────────
    //  TABLE STACK
    // ──────────────────────────────────────────────────────

    /**
     * Table component stack schema
     *
     * Renders paginated, sortable data tables with row actions and bulk actions.
     */
    public static function tableSchema(): array
    {
        return [
            'type'        => 'table',
            'description' => 'Paginated, sortable data table with row actions',
            'icon'        => 'lucide-table',
            'configSchema' => [
                'type'       => 'object',
                'required'   => ['columns'],
                'properties' => [
                    'id' => [
                        'type'        => 'string',
                        'description' => 'CSS ID for targeting (e.g., from filters)',
                        'pattern'     => '^[a-z][a-z0-9-]*$',
                    ],
                    'columns' => [
                        'type'        => 'array',
                        'description' => 'Table column definitions',
                        'items'       => self::tableColumnSchema(),
                        'minItems'    => 1,
                        'maxItems'    => 30,
                    ],
                    'actions' => [
                        'type'        => 'array',
                        'description' => 'Row action buttons (view, edit, delete, etc.)',
                        'items'       => self::rowActionSchema(),
                        'maxItems'    => 6,
                    ],
                    'bulkActions' => [
                        'type'        => 'array',
                        'description' => 'Bulk action buttons for selected rows',
                        'items'       => self::bulkActionSchema(),
                        'maxItems'    => 5,
                    ],
                    'pagination' => [
                        'type'       => 'object',
                        'properties' => [
                            'perPage' => [
                                'type' => 'integer', 'minimum' => 5, 'maximum' => 500, 'default' => 50,
                            ],
                            'pageSizes' => [
                                'type' => 'array', 'items' => ['type' => 'integer'],
                                'default' => [25, 50, 100],
                            ],
                        ],
                    ],
                    'emptyMessage' => [
                        'type' => 'string', 'default' => 'No records found',
                    ],
                    'exportable' => [
                        'type' => 'boolean', 'description' => 'Show export button', 'default' => false,
                    ],
                ],
            ],
        ];
    }

    private static function tableColumnSchema(): array
    {
        return [
            'type'       => 'object',
            'required'   => ['key', 'label'],
            'properties' => [
                'key' => [
                    'type' => 'string',
                    'description' => 'Database column name or computed field key',
                ],
                'label' => [
                    'type' => 'string',
                    'description' => 'Column header text',
                    'maxLength' => 50,
                ],
                'sortable' => [
                    'type' => 'boolean', 'default' => false,
                ],
                'type' => [
                    'type' => 'string',
                    'description' => 'Display renderer for the cell value',
                    'enum' => [
                        'text', 'number', 'date', 'datetime', 'currency', 'email',
                        'badge', 'boolean', 'link', 'image', 'percentage', 'masked',
                    ],
                    'default' => 'text',
                ],
                'width' => [
                    'type' => 'string',
                    'description' => 'CSS width (e.g., "120px", "15%")',
                ],
                'badgeMap' => [
                    'type'        => 'object',
                    'description' => 'Maps cell values to badge colors (for type=badge)',
                    'additionalProperties' => ['type' => 'string'],
                ],
                'hidden' => [
                    'type' => 'boolean', 'default' => false,
                    'description' => 'Column exists in data but not rendered visually',
                ],
                'copyable' => [
                    'type' => 'boolean', 'default' => false,
                ],
                'truncate' => [
                    'type' => 'integer',
                    'description' => 'Max character length before truncation',
                    'minimum' => 10,
                ],
            ],
        ];
    }

    private static function rowActionSchema(): array
    {
        return [
            'type'       => 'object',
            'required'   => ['label', 'type'],
            'properties' => [
                'label'   => ['type' => 'string'],
                'icon'    => ['type' => 'string'],
                'type'    => [
                    'type' => 'string',
                    'enum' => ['route', 'mutation', 'confirm', 'modal'],
                ],
                'to'      => ['type' => 'string', 'description' => 'Route with {id} placeholder'],
                'mutation' => ['type' => 'string', 'description' => 'Mutation name for action type=mutation'],
                'variant' => [
                    'type' => 'string',
                    'enum' => ['primary', 'success', 'warning', 'danger', 'info', 'secondary', 'outline-primary', 'outline-danger'],
                    'default' => 'primary',
                ],
                'confirm' => ['type' => 'string', 'description' => 'Confirmation message text'],
                'requiredRole' => ['type' => 'string'],
            ],
        ];
    }

    private static function bulkActionSchema(): array
    {
        return [
            'type'       => 'object',
            'required'   => ['label', 'mutation'],
            'properties' => [
                'label'    => ['type' => 'string'],
                'icon'     => ['type' => 'string'],
                'mutation' => ['type' => 'string'],
                'variant'  => ['type' => 'string', 'default' => 'primary'],
                'confirm'  => ['type' => 'string'],
            ],
        ];
    }

    // ──────────────────────────────────────────────────────
    //  FORM STACK
    // ──────────────────────────────────────────────────────

    /**
     * Form component stack schema
     *
     * Renders create/edit forms with sections, grid layout, field validation.
     */
    public static function formSchema(): array
    {
        return [
            'type'        => 'form',
            'description' => 'Create/edit forms with sections, grid layout, and validation',
            'icon'        => 'lucide-clipboard-edit',
            'configSchema' => [
                'type'       => 'object',
                'required'   => ['mutation'],
                'properties' => [
                    'mutation' => [
                        'type'        => 'string',
                        'description' => 'Mutation name to call on submit (must exist in data.mutations)',
                    ],
                    'layout' => [
                        'type' => 'string',
                        'enum' => ['vertical', 'horizontal', 'inline'],
                        'default' => 'vertical',
                    ],
                    'submitLabel' => [
                        'type' => 'string', 'default' => 'Save', 'maxLength' => 30,
                    ],
                    'cancelUrl' => [
                        'type' => 'string', 'description' => 'Route to navigate on cancel',
                    ],
                    'sections' => [
                        'type'        => 'array',
                        'description' => 'Form sections (groups of fields with optional grid layout)',
                        'items'       => self::formSectionSchema(),
                        'maxItems'    => 20,
                    ],
                    'fields' => [
                        'type'        => 'array',
                        'description' => 'Flat field list (use sections OR fields, not both)',
                        'items'       => self::fieldSchema(),
                        'maxItems'    => 100,
                    ],
                ],
                'oneOf' => [
                    ['required' => ['sections']],
                    ['required' => ['fields']],
                ],
            ],
        ];
    }

    private static function formSectionSchema(): array
    {
        return [
            'type'       => 'object',
            'required'   => ['title', 'fields'],
            'properties' => [
                'title' => [
                    'type' => 'string', 'description' => 'Section heading', 'maxLength' => 100,
                ],
                'description' => [
                    'type' => 'string', 'description' => 'Section help text',
                ],
                'grid' => [
                    'type'    => 'integer',
                    'description' => 'Number of columns in this section grid (1-4)',
                    'minimum' => 1,
                    'maximum' => 4,
                    'default' => 1,
                ],
                'collapsible' => [
                    'type' => 'boolean', 'default' => false,
                ],
                'collapsed' => [
                    'type' => 'boolean', 'default' => false,
                    'description' => 'Only applies when collapsible=true',
                ],
                'fields' => [
                    'type'    => 'array',
                    'items'   => self::fieldSchema(),
                    'minItems' => 1,
                    'maxItems' => 50,
                ],
            ],
        ];
    }

    // ──────────────────────────────────────────────────────
    //  FIELD TYPES (shared between form & detail)
    // ──────────────────────────────────────────────────────

    /**
     * Field schema — the atomic building block of forms
     *
     * Every field type has defined rules about what properties
     * are required, optional, and how validation works.
     */
    public static function fieldSchema(): array
    {
        return [
            'type'       => 'object',
            'required'   => ['key', 'type', 'label'],
            'properties' => [
                'key' => [
                    'type'        => 'string',
                    'description' => 'Database column name / form field name',
                    'pattern'     => '^[a-zA-Z_][a-zA-Z0-9_]*$',
                ],
                'type' => [
                    'type'        => 'string',
                    'description' => 'Field input type',
                    'enum'        => array_keys(self::fieldTypeRules()),
                ],
                'label' => [
                    'type'      => 'string',
                    'maxLength' => 100,
                ],
                'required' => [
                    'type' => 'boolean', 'default' => false,
                ],
                'placeholder' => [
                    'type' => 'string', 'maxLength' => 200,
                ],
                'helpText' => [
                    'type' => 'string', 'description' => 'Small help text below field',
                ],
                'default' => [
                    'description' => 'Default value (type varies by field type)',
                ],
                'hidden' => [
                    'type' => 'boolean', 'default' => false,
                ],
                'readOnly' => [
                    'type' => 'boolean', 'default' => false,
                ],
                'span' => [
                    'type'    => 'integer',
                    'description' => 'Grid column span (within section grid). E.g., span=2 in a grid=3 section.',
                    'minimum' => 1,
                    'maximum' => 4,
                ],
                'fullWidth' => [
                    'type' => 'boolean', 'default' => false,
                    'description' => 'Span the full width of the grid (alternative to span)',
                ],
                'validation' => [
                    'type'       => 'object',
                    'description' => 'Client-side and server-side validation rules',
                    'properties' => [
                        'min'       => ['type' => 'number'],
                        'max'       => ['type' => 'number'],
                        'minLength' => ['type' => 'integer', 'minimum' => 0],
                        'maxLength' => ['type' => 'integer', 'minimum' => 1],
                        'pattern'   => ['type' => 'string', 'description' => 'Regex pattern'],
                        'step'      => ['type' => 'number'],
                    ],
                ],
                // Select/radio-specific
                'options' => [
                    'type'  => 'array',
                    'description' => 'Static options for select/radio fields',
                    'items' => [
                        'type'       => 'object',
                        'required'   => ['value', 'label'],
                        'properties' => [
                            'value' => ['type' => 'string'],
                            'label' => ['type' => 'string'],
                        ],
                    ],
                ],
                'optionsQuery' => [
                    'type' => 'string',
                    'description' => 'Query name to load options dynamically',
                ],
                'multiple' => [
                    'type' => 'boolean', 'default' => false,
                    'description' => 'Allow multiple selection (select only)',
                ],
                // Textarea specific
                'rows' => [
                    'type' => 'integer', 'minimum' => 1, 'maximum' => 30, 'default' => 4,
                ],
                // File-specific
                'accept' => [
                    'type' => 'string', 'description' => 'MIME types for file upload',
                    'examples' => ['image/*', '.pdf,.doc,.docx', 'application/pdf'],
                ],
                // Number-specific
                'step' => ['type' => 'string', 'default' => 'any'],
            ],
        ];
    }

    /**
     * Field type rules — defines which properties apply to each field type
     *
     * This is the BOUNDARIES layer: each field type has specific
     * allowed and required properties. The validator enforces these.
     *
     * @return array<string, array> type => { allowed: string[], required: string[], description: string }
     */
    public static function fieldTypeRules(): array
    {
        return [
            'text' => [
                'description'  => 'Single-line text input',
                'allowed'      => ['validation', 'placeholder'],
                'validationKeys' => ['minLength', 'maxLength', 'pattern'],
            ],
            'textarea' => [
                'description'    => 'Multi-line text area',
                'allowed'        => ['validation', 'placeholder', 'rows'],
                'validationKeys' => ['minLength', 'maxLength'],
            ],
            'number' => [
                'description'    => 'Numeric input',
                'allowed'        => ['validation', 'step', 'placeholder'],
                'validationKeys' => ['min', 'max', 'step'],
            ],
            'currency' => [
                'description'    => 'Dollar amount input ($ prefix, 0.01 step)',
                'allowed'        => ['validation', 'placeholder'],
                'validationKeys' => ['min', 'max'],
            ],
            'email' => [
                'description'    => 'Email address input',
                'allowed'        => ['validation', 'placeholder'],
                'validationKeys' => ['maxLength'],
            ],
            'date' => [
                'description'    => 'Date picker (YYYY-MM-DD)',
                'allowed'        => ['validation'],
                'validationKeys' => ['min', 'max'],
            ],
            'datetime' => [
                'description'    => 'Date and time picker',
                'allowed'        => ['validation'],
                'validationKeys' => ['min', 'max'],
            ],
            'select' => [
                'description'    => 'Dropdown select',
                'allowed'        => ['options', 'optionsQuery', 'multiple', 'placeholder'],
                'required'       => [],
                'validationKeys' => [],
                'notes'          => 'Must have either options (static) or optionsQuery (dynamic), not neither.',
            ],
            'radio' => [
                'description' => 'Radio button group',
                'allowed'     => ['options'],
                'required'    => ['options'],
            ],
            'checkbox' => [
                'description' => 'Single boolean checkbox',
                'allowed'     => [],
            ],
            'file' => [
                'description' => 'File upload',
                'allowed'     => ['accept'],
            ],
            'password' => [
                'description'    => 'Password input (masked)',
                'allowed'        => ['validation', 'placeholder'],
                'validationKeys' => ['minLength', 'maxLength'],
            ],
            'url' => [
                'description'    => 'URL input',
                'allowed'        => ['validation', 'placeholder'],
                'validationKeys' => ['maxLength'],
            ],
            'tel' => [
                'description'    => 'Telephone input',
                'allowed'        => ['validation', 'placeholder'],
                'validationKeys' => ['pattern'],
            ],
            'hidden' => [
                'description' => 'Hidden form field (not rendered visually)',
                'allowed'     => ['default'],
            ],
        ];
    }

    // ──────────────────────────────────────────────────────
    //  DETAIL STACK
    // ──────────────────────────────────────────────────────

    /**
     * Detail component stack schema
     *
     * Renders single-record read-only views with sections and field display types.
     */
    public static function detailSchema(): array
    {
        return [
            'type'         => 'detail',
            'description'  => 'Single-record detail view with sections',
            'icon'         => 'lucide-file-text',
            'configSchema' => [
                'type'       => 'object',
                'properties' => [
                    'sections' => [
                        'type'  => 'array',
                        'items' => self::detailSectionSchema(),
                        'maxItems' => 20,
                    ],
                    'actions' => [
                        'type'  => 'array',
                        'description' => 'Header action buttons (edit, delete, etc.)',
                        'items' => self::rowActionSchema(),
                        'maxItems' => 6,
                    ],
                    'backUrl' => [
                        'type' => 'string', 'description' => 'Route for "Back" button',
                    ],
                ],
            ],
        ];
    }

    private static function detailSectionSchema(): array
    {
        return [
            'type'       => 'object',
            'required'   => ['title', 'fields'],
            'properties' => [
                'title'   => ['type' => 'string', 'maxLength' => 100],
                'columns' => [
                    'type' => 'integer', 'minimum' => 1, 'maximum' => 4, 'default' => 2,
                    'description' => 'Number of columns in field grid',
                ],
                'fields' => [
                    'type'  => 'array',
                    'items' => self::detailFieldSchema(),
                    'minItems' => 1,
                    'maxItems' => 50,
                ],
            ],
        ];
    }

    private static function detailFieldSchema(): array
    {
        return [
            'type'       => 'object',
            'required'   => ['key', 'label'],
            'properties' => [
                'key'   => ['type' => 'string'],
                'label' => ['type' => 'string'],
                'type'  => [
                    'type' => 'string',
                    'enum' => [
                        'text', 'date', 'datetime', 'email', 'link', 'badge',
                        'boolean', 'currency', 'masked', 'long_text', 'list', 'image',
                    ],
                    'default' => 'text',
                ],
                'copyable'   => ['type' => 'boolean', 'default' => false],
                'badgeColor' => ['type' => 'string', 'enum' => ['primary', 'success', 'warning', 'danger', 'info', 'secondary']],
            ],
        ];
    }

    // ──────────────────────────────────────────────────────
    //  FILTERS STACK
    // ──────────────────────────────────────────────────────

    /**
     * Filters component stack schema
     *
     * Renders search/filter bars that target a table component.
     */
    public static function filtersSchema(): array
    {
        return [
            'type'         => 'filters',
            'description'  => 'Search and filter bar targeting a data table',
            'icon'         => 'lucide-filter',
            'configSchema' => [
                'type'       => 'object',
                'required'   => ['filters'],
                'properties' => [
                    'targetTable' => [
                        'type'        => 'string',
                        'description' => 'CSS ID of the table component these filters control',
                    ],
                    'filters' => [
                        'type'  => 'array',
                        'items' => self::filterFieldSchema(),
                        'minItems' => 1,
                        'maxItems' => 10,
                    ],
                ],
            ],
        ];
    }

    private static function filterFieldSchema(): array
    {
        return [
            'type'       => 'object',
            'required'   => ['name', 'type'],
            'properties' => [
                'name' => [
                    'type' => 'string', 'description' => 'URL parameter name',
                ],
                'type' => [
                    'type' => 'string',
                    'enum' => ['search', 'select', 'date', 'date_range', 'checkbox', 'text'],
                ],
                'label' => ['type' => 'string', 'maxLength' => 50],
                'placeholder' => ['type' => 'string'],
                'debounce' => [
                    'type' => 'integer', 'minimum' => 0, 'maximum' => 2000,
                    'description' => 'Debounce delay in ms (for search type)',
                ],
                'options' => [
                    'type'  => 'array',
                    'description' => 'Static options for select filter',
                    'items' => [
                        'type'     => 'object',
                        'required' => ['value', 'label'],
                        'properties' => [
                            'value' => ['type' => 'string'],
                            'label' => ['type' => 'string'],
                        ],
                    ],
                ],
                'optionsQuery' => [
                    'type' => 'string',
                    'description' => 'Query name to load filter options dynamically',
                ],
                'allowAll' => [
                    'type' => 'boolean', 'default' => true,
                ],
                'startParam' => ['type' => 'string', 'description' => 'For date_range: start param name'],
                'endParam'   => ['type' => 'string', 'description' => 'For date_range: end param name'],
            ],
        ];
    }

    // ──────────────────────────────────────────────────────
    //  QUERY & MUTATION SCHEMAS
    // ──────────────────────────────────────────────────────

    /**
     * Query definition schema (data.queries.*)
     */
    public static function querySchema(): array
    {
        return [
            'type'       => 'object',
            'required'   => ['handler'],
            'properties' => [
                'handler' => [
                    'type' => 'string',
                    'description' => 'Query handler name. GenericHandler infers type: list*, get*Stats, get*, options, count',
                    'examples' => ['listStudents', 'getStudent', 'getStats', 'getGrades', 'getDepartments'],
                ],
                'table' => [
                    'type' => 'string',
                    'description' => 'Override primaryTable for this query',
                ],
                'select' => [
                    'type' => 'string',
                    'description' => 'Override SELECT columns (raw SQL fragment)',
                    'examples' => ['DISTINCT grade as value, grade as label', 'COUNT(*) as total'],
                ],
                'searchColumns' => [
                    'type'  => 'array',
                    'description' => 'Columns searchable by the search filter',
                    'items' => ['type' => 'string'],
                ],
                'defaultSort' => [
                    'type'       => 'object',
                    'properties' => [
                        'column'    => ['type' => 'string'],
                        'direction' => ['type' => 'string', 'enum' => ['ASC', 'DESC']],
                    ],
                ],
                'filters' => [
                    'type'        => 'object',
                    'description' => 'Available filter parameters mapped to column names',
                    'additionalProperties' => ['type' => 'string'],
                ],
                'where' => [
                    'type'        => 'string',
                    'description' => 'Static WHERE clause (e.g., "is_active = 1")',
                ],
            ],
        ];
    }

    /**
     * Mutation definition schema (data.mutations.*)
     */
    public static function mutationSchema(): array
    {
        return [
            'type'       => 'object',
            'required'   => ['handler'],
            'properties' => [
                'handler' => [
                    'type' => 'string',
                    'description' => 'Mutation handler name. GenericHandler infers: create*, update*, delete*, toggle*, approve*, reject*',
                    'examples' => ['createStudent', 'updateStudent', 'deleteStudent', 'toggleActive'],
                ],
                'table' => [
                    'type' => 'string',
                    'description' => 'Override primaryTable for this mutation',
                ],
                'requiredRole' => [
                    'type' => 'string',
                    'description' => 'Minimum package role required to execute',
                ],
                'fields' => [
                    'type'  => 'array',
                    'description' => 'Whitelist of allowed fields for insert/update',
                    'items' => ['type' => 'string'],
                ],
                'audit' => [
                    'type' => 'boolean', 'default' => true,
                    'description' => 'Whether to log this mutation to audit table',
                ],
                'softDelete' => [
                    'type' => 'boolean', 'default' => true,
                    'description' => 'For delete mutations: use is_active=0 instead of DELETE',
                ],
            ],
        ];
    }

    // ──────────────────────────────────────────────────────
    //  WORKFLOW SCHEMA (Layer 2)
    // ──────────────────────────────────────────────────────

    public static function workflowSchema(): array
    {
        return [
            'type'       => 'object',
            'description' => 'Layer 2 approval workflow',
            'properties' => [
                'states' => [
                    'type'  => 'array',
                    'description' => 'All possible workflow states',
                    'items' => [
                        'type'       => 'object',
                        'required'   => ['id', 'label'],
                        'properties' => [
                            'id'    => ['type' => 'string'],
                            'label' => ['type' => 'string'],
                            'color' => ['type' => 'string'],
                            'icon'  => ['type' => 'string'],
                        ],
                    ],
                ],
                'initialState' => [
                    'type' => 'string', 'description' => 'State ID for new records',
                ],
                'transitions' => [
                    'type'  => 'array',
                    'description' => 'Allowed state transitions',
                    'items' => [
                        'type'       => 'object',
                        'required'   => ['from', 'to', 'action'],
                        'properties' => [
                            'from'         => ['type' => 'string'],
                            'to'           => ['type' => 'string'],
                            'action'       => ['type' => 'string', 'description' => 'Button label'],
                            'requiredRole' => ['type' => 'string'],
                            'icon'         => ['type' => 'string'],
                            'variant'      => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    // ──────────────────────────────────────────────────────
    //  UTILITY: Get summary for wizard/documentation
    // ──────────────────────────────────────────────────────

    /**
     * Get a human-readable summary of all stack types
     *
     * Used by the package creator wizard and CONTRIBUTING.md generator.
     *
     * @return array<string, array{description: string, icon: string, requiredConfig: string[], optionalConfig: string[]}>
     */
    public static function getStackSummary(): array
    {
        $schemas = self::getAllSchemas();
        $summary = [];

        foreach ($schemas as $type => $schema) {
            if ($type === 'cards') continue; // skip alias

            $configSchema = $schema['configSchema'] ?? [];
            $required = $configSchema['required'] ?? [];
            $props = $configSchema['properties'] ?? [];
            $optional = array_diff(array_keys($props), $required);

            $summary[$type] = [
                'description'    => $schema['description'] ?? '',
                'icon'           => $schema['icon'] ?? '',
                'requiredConfig' => $required,
                'optionalConfig' => array_values($optional),
            ];
        }

        return $summary;
    }

    /**
     * Get the list of all valid field types with descriptions
     *
     * @return array<string, string> type => description
     */
    public static function getFieldTypeList(): array
    {
        $types = [];
        foreach (self::fieldTypeRules() as $type => $rule) {
            $types[$type] = $rule['description'] ?? $type;
        }
        return $types;
    }
}
