<?php
/**
 * Package Schema API
 *
 * Endpoints for package validation, schema discovery, and package scaffolding.
 * Used by the package creator wizard and external tools.
 *
 * GET  ?action=stacks           → List all stack types with rules
 * GET  ?action=field-types      → List all valid field types
 * GET  ?action=schema           → Full package schema definition
 * POST ?action=validate         → Validate a package.json
 * POST ?action=scaffold         → Generate package.json from wizard input
 *
 * @see src/Package/Schema/ComponentSchema.php
 * @see src/Package/Schema/PackageSchemaValidator.php
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\Package\Schema\ComponentSchema;
use Hub\Package\Schema\PackageSchemaValidator;

// Require login for all schema API calls
Auth::requireLogin();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        // ── GET: List stack types ────────────────────────────
        case 'stacks':
            if ($method !== 'GET') methodNotAllowed();
            jsonResponse(ComponentSchema::getStackSummary());
            break;

        // ── GET: List field types ────────────────────────────
        case 'field-types':
            if ($method !== 'GET') methodNotAllowed();
            jsonResponse([
                'fieldTypes' => ComponentSchema::getFieldTypeList(),
                'fieldTypeRules' => ComponentSchema::fieldTypeRules(),
            ]);
            break;

        // ── GET: Full schema ─────────────────────────────────
        case 'schema':
            if ($method !== 'GET') methodNotAllowed();
            $type = $_GET['type'] ?? null;
            if ($type) {
                jsonResponse(ComponentSchema::getSchema($type));
            } else {
                jsonResponse([
                    'packageSchema'   => ComponentSchema::packageSchema(),
                    'componentSchemas' => ComponentSchema::getAllSchemas(),
                    'fieldTypes'      => ComponentSchema::getFieldTypeList(),
                ]);
            }
            break;

        // ── POST: Validate package JSON ──────────────────────
        case 'validate':
            if ($method !== 'POST') methodNotAllowed();
            verifyCsrfToken();

            $input = json_decode(file_get_contents('php://input'), true);
            $packageData = $input['package'] ?? $input;

            if (empty($packageData) || !is_array($packageData)) {
                jsonResponse(['error' => 'Invalid or empty package JSON'], 400);
            }

            $validator = new PackageSchemaValidator();
            $result = $validator->validate($packageData);

            jsonResponse($result);
            break;

        // ── POST: Scaffold package from wizard ───────────────
        case 'scaffold':
            if ($method !== 'POST') methodNotAllowed();
            verifyCsrfToken();

            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($input)) {
                jsonResponse(['error' => 'Missing wizard input'], 400);
            }

            $scaffold = buildScaffold($input);

            // Validate the generated scaffold
            $validator = new PackageSchemaValidator();
            $result = $validator->validate($scaffold);

            jsonResponse([
                'package'    => $scaffold,
                'validation' => $result,
                'json'       => json_encode($scaffold, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ]);
            break;

        default:
            jsonResponse(['error' => "Unknown action: '{$action}'"], 400);
    }
} catch (\InvalidArgumentException $e) {
    jsonResponse(['error' => $e->getMessage()], 400);
} catch (\Exception $e) {
    error_log("[package-schema API] " . $e->getMessage());
    jsonResponse(['error' => 'Internal server error'], 500);
}

// ──────────────────────────────────────────────────────
//  SCAFFOLD BUILDER
// ──────────────────────────────────────────────────────

/**
 * Build a complete package.json scaffold from wizard inputs
 */
function buildScaffold(array $input): array
{
    $name = $input['name'] ?? 'My Package';
    $category = $input['category'] ?? 'local';
    $id = $category . '.' . slugify($name);
    $dbName = $input['database'] ?? str_replace('-', '_', slugify($name));
    $tableName = $input['tableName'] ?? str_replace('-', '_', slugify($name));

    $scaffold = [
        'schemaVersion' => '3.0.0',
        'package' => [
            'id'           => $id,
            'display_name' => $name,
            'description'  => $input['description'] ?? '',
            'version'      => '1.0.0',
            'author'       => $input['author'] ?? 'Woodson ISD',
            'icon'         => $input['icon'] ?? 'lucide-package',
            'category'     => $category,
            'base_url'     => '/p/' . $id,
        ],
        'database' => [
            'connection'   => $dbName,
            'primaryTable' => $tableName,
        ],
        'presentation' => [
            'pages' => [],
        ],
        'data' => [
            'queries'   => [],
            'mutations' => [],
        ],
        'policy' => [
            'roles'             => ['viewer', 'user', 'manager', 'admin'],
            'defaultRole'       => $input['defaultRole'] ?? 'user',
            'globalRoleMapping' => [
                'super_admin' => 'admin',
                'admin'       => 'admin',
                'manager'     => 'manager',
                'user'        => 'user',
                'viewer'      => 'viewer',
            ],
        ],
    ];

    // Build pages from wizard page definitions
    $pages = $input['pages'] ?? [];

    if (empty($pages)) {
        // Default: index page with dashboard + filters + table
        $pages = [
            [
                'id'         => 'index',
                'title'      => $name,
                'route'      => '/',
                'components' => ['dashboard', 'filters', 'table'],
            ],
        ];
    }

    foreach ($pages as $pageDef) {
        $pageId = $pageDef['id'] ?? 'page-' . count($scaffold['presentation']['pages']);
        $scaffold['presentation']['pages'][$pageId] = buildPage($pageDef, $input, $tableName);
    }

    // Build queries based on components used
    $scaffold['data']['queries'] = buildQueries($input, $tableName, $scaffold['presentation']['pages']);

    // Build mutations based on forms
    $scaffold['data']['mutations'] = buildMutations($input, $tableName, $scaffold['presentation']['pages']);

    return $scaffold;
}

function buildPage(array $pageDef, array $input, string $tableName): array
{
    $page = [
        'title'      => $pageDef['title'] ?? 'Untitled Page',
        'route'      => $pageDef['route'] ?? '/',
        'layout'     => $pageDef['layout'] ?? 'full',
        'components' => [],
    ];

    $componentTypes = $pageDef['components'] ?? [];
    $fields = $input['fields'] ?? [];

    foreach ($componentTypes as $compType) {
        if (is_string($compType)) {
            // Short-hand: just type name → auto-build config
            $page['components'][] = buildDefaultComponent($compType, $fields, $tableName, $input);
        } elseif (is_array($compType)) {
            // Full component definition from wizard
            $page['components'][] = $compType;
        }
    }

    return $page;
}

function buildDefaultComponent(string $type, array $fields, string $tableName, array $input): array
{
    $name = $input['name'] ?? 'Record';
    $singular = rtrim($name, 's');

    switch ($type) {
        case 'dashboard':
            return [
                'type'      => 'dashboard',
                'dataQuery' => 'getStats',
                'config'    => [
                    'columns' => 4,
                    'cards'   => [
                        [
                            'title'   => "Total {$name}",
                            'dataKey' => 'total',
                            'icon'    => $input['icon'] ?? 'lucide-hash',
                            'color'   => 'primary',
                        ],
                    ],
                ],
            ];

        case 'filters':
            $filters = [
                [
                    'name'        => 'search',
                    'type'        => 'search',
                    'placeholder' => "Search {$name}...",
                    'debounce'    => 300,
                ],
            ];
            // Add select filters for fields with options
            foreach ($fields as $field) {
                if (($field['type'] ?? '') === 'select' && !empty($field['options'])) {
                    $filters[] = [
                        'name'    => $field['key'] ?? $field['name'] ?? '',
                        'type'    => 'select',
                        'label'   => $field['label'] ?? '',
                        'options' => $field['options'],
                    ];
                }
            }
            return [
                'type'   => 'filters',
                'config' => [
                    'targetTable' => 'pkg-table-' . slugify($name),
                    'filters'     => $filters,
                ],
            ];

        case 'table':
            $columns = [];
            foreach ($fields as $field) {
                if ($field['hidden'] ?? false) continue;
                $columns[] = [
                    'key'      => $field['key'] ?? $field['name'] ?? '',
                    'label'    => $field['label'] ?? '',
                    'sortable' => true,
                    'type'     => mapFieldToColumnType($field['type'] ?? 'text'),
                ];
            }
            return [
                'type'      => 'table',
                'dataQuery' => 'list' . ucfirst(camelize($name)),
                'config'    => [
                    'id'         => 'pkg-table-' . slugify($name),
                    'columns'    => $columns ?: [['key' => 'id', 'label' => 'ID', 'sortable' => true]],
                    'actions'    => [
                        ['label' => 'View', 'icon' => 'lucide-eye', 'type' => 'route', 'to' => '/' . slugify($singular) . '/{id}'],
                    ],
                    'pagination' => ['perPage' => 50],
                ],
            ];

        case 'form':
            $formFields = [];
            foreach ($fields as $field) {
                $formFields[] = [
                    'key'         => $field['key'] ?? $field['name'] ?? '',
                    'type'        => $field['type'] ?? 'text',
                    'label'       => $field['label'] ?? '',
                    'required'    => $field['required'] ?? false,
                    'placeholder' => $field['placeholder'] ?? '',
                ];
                if (!empty($field['options'])) {
                    $formFields[count($formFields) - 1]['options'] = $field['options'];
                }
            }
            return [
                'type'   => 'form',
                'config' => [
                    'mutation'    => 'create' . ucfirst(camelize($singular)),
                    'submitLabel' => 'Save ' . $singular,
                    'cancelUrl'   => '/',
                    'sections'    => [
                        [
                            'title'  => $singular . ' Information',
                            'grid'   => 2,
                            'fields' => $formFields ?: [
                                ['key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true],
                            ],
                        ],
                    ],
                ],
            ];

        case 'detail':
            $detailFields = [];
            foreach ($fields as $field) {
                if ($field['hidden'] ?? false) continue;
                $detailFields[] = [
                    'key'   => $field['key'] ?? $field['name'] ?? '',
                    'label' => $field['label'] ?? '',
                    'type'  => mapFieldToDetailType($field['type'] ?? 'text'),
                ];
            }
            return [
                'type'   => 'detail',
                'config' => [
                    'backUrl'  => '/',
                    'sections' => [
                        [
                            'title'   => $singular . ' Details',
                            'columns' => 2,
                            'fields'  => $detailFields ?: [['key' => 'id', 'label' => 'ID']],
                        ],
                    ],
                    'actions' => [
                        ['label' => 'Edit', 'icon' => 'lucide-edit', 'type' => 'route', 'to' => '/' . slugify($singular) . '/{id}/edit'],
                    ],
                ],
            ];

        default:
            return ['type' => $type, 'config' => []];
    }
}

function buildQueries(array $input, string $tableName, array $pages): array
{
    $queries = [];
    $name = $input['name'] ?? 'Records';
    $singular = rtrim($name, 's');
    $fields = $input['fields'] ?? [];

    // Scan pages for dataQuery references
    $usedQueries = [];
    foreach ($pages as $page) {
        foreach ($page['components'] ?? [] as $comp) {
            if (!empty($comp['dataQuery'])) {
                $usedQueries[] = $comp['dataQuery'];
            }
        }
    }

    // Build search columns from text-like fields
    $searchCols = [];
    foreach ($fields as $field) {
        $type = $field['type'] ?? 'text';
        if (in_array($type, ['text', 'email', 'tel', 'url'])) {
            $searchCols[] = $field['key'] ?? $field['name'] ?? '';
        }
    }

    // Stats query
    if (in_array('getStats', $usedQueries) || true) {
        $queries['getStats'] = [
            'handler' => 'getStats',
            'table'   => $tableName,
        ];
    }

    // List query
    $listName = 'list' . ucfirst(camelize($name));
    if (in_array($listName, $usedQueries) || true) {
        $queries[$listName] = [
            'handler'       => $listName,
            'table'         => $tableName,
            'searchColumns' => $searchCols ?: ['id'],
        ];
    }

    // Single record query
    $getName = 'get' . ucfirst(camelize($singular));
    $queries[$getName] = [
        'handler' => $getName,
        'table'   => $tableName,
    ];

    return $queries;
}

function buildMutations(array $input, string $tableName, array $pages): array
{
    $mutations = [];
    $name = $input['name'] ?? 'Records';
    $singular = rtrim($name, 's');
    $camel = ucfirst(camelize($singular));

    // Scan pages for mutation references
    foreach ($pages as $page) {
        foreach ($page['components'] ?? [] as $comp) {
            if (($comp['type'] ?? '') === 'form' && !empty($comp['config']['mutation'])) {
                $mutations[$comp['config']['mutation']] = [
                    'handler' => $comp['config']['mutation'],
                    'table'   => $tableName,
                ];
            }
        }
    }

    // Always add create/update/delete
    if (!isset($mutations["create{$camel}"])) {
        $mutations["create{$camel}"] = ['handler' => "create{$camel}", 'table' => $tableName];
    }
    $mutations["update{$camel}"] = ['handler' => "update{$camel}", 'table' => $tableName];
    $mutations["delete{$camel}"] = ['handler' => "delete{$camel}", 'table' => $tableName, 'softDelete' => true];

    return $mutations;
}

// ── Helpers ──────────────────────────────────────────────

function slugify(string $text): string
{
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}

function camelize(string $text): string
{
    return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $text)));
}

function mapFieldToColumnType(string $fieldType): string
{
    return match ($fieldType) {
        'date'     => 'date',
        'datetime' => 'datetime',
        'number', 'currency' => $fieldType,
        'email'    => 'email',
        'checkbox' => 'boolean',
        'select'   => 'badge',
        default    => 'text',
    };
}

function mapFieldToDetailType(string $fieldType): string
{
    return match ($fieldType) {
        'date'     => 'date',
        'datetime' => 'datetime',
        'currency' => 'currency',
        'email'    => 'email',
        'checkbox' => 'boolean',
        'textarea' => 'long_text',
        default    => 'text',
    };
}

function methodNotAllowed(): void
{
    http_response_code(405);
    jsonResponse(['error' => 'Method not allowed']);
    exit;
}
