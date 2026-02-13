<?php

namespace Hub\Package;

use Hub\Auth;
use Hub\Database;
use Hub\AuditLogger;
use Hub\RequestContext;

/**
 * Package Page Router
 *
 * The catch-all front controller for Layer 3 packages.
 * Routes /p/{packageId}/{pageId} URLs to JSON-driven page rendering.
 *
 * Responsibilities:
 * 1. Parse URL → resolve package + page
 * 2. Check access (role-based via PolicyEngine)
 * 3. Execute queries through QueryRouter (enforcement pipeline)
 * 4. Render components via ComponentRegistry
 * 5. Assemble final HTML with package layout
 *
 * @see PACKAGE_ARCHITECTURE_SPEC.md §5 (Cards → Mini Website Model)
 */
class PageRouter
{
    private PackageLoader $loader;
    private ComponentRegistry $components;
    private PolicyEngine $policy;
    private ?QueryRouter $queryRouter;
    private ?MutationRouter $mutationRouter;

    /** @var array<string, callable> Direct query handlers by packageId */
    private array $directHandlers = [];

    public function __construct(
        ?PackageLoader $loader = null,
        ?ComponentRegistry $components = null,
        ?PolicyEngine $policy = null,
        ?QueryRouter $queryRouter = null,
        ?MutationRouter $mutationRouter = null
    ) {
        $this->loader = $loader ?? new PackageLoader();
        $this->components = $components ?? ComponentRegistry::getInstance();
        $this->policy = $policy ?? new PolicyEngine();
        $this->queryRouter = $queryRouter;
        $this->mutationRouter = $mutationRouter;
    }

    /**
     * Register a direct query handler for a package.
     * Used during Sprint 1 before full QueryRouter integration.
     *
     * Handler callable signature: function(string $queryName, array $params, array $context): array
     */
    public function registerHandler(string $packageId, callable $handler): void
    {
        $this->directHandlers[$packageId] = $handler;
    }

    /**
     * Handle a package page request
     *
     * @param string $packageId Package identifier (e.g., 'com.woodson.student-directory')
     * @param string $pageId Page identifier or route tail
     * @param array $user Current authenticated user
     * @param array $params Request parameters (GET + POST)
     * @return array Render result: ['html' => string, 'title' => string, 'assets' => array, 'status' => int]
     */
    public function handle(string $packageId, string $pageId, array $user, array $params = []): array
    {
        // 1. Load package
        $packageData = $this->loader->load($packageId);
        if (!$packageData) {
            return $this->errorResponse(404, 'Package Not Found', "Package '{$packageId}' is not installed or does not exist.");
        }

        // 2. Find the page
        $pageConfig = $this->findPageByRoute($packageData, $pageId);
        if (!$pageConfig) {
            return $this->errorResponse(404, 'Page Not Found', "The requested page does not exist in this package.");
        }

        // 3. Check page access via PackageAccessResolver
        //    Maps system roles (super_admin, admin, user) → package roles (viewer, editor, importer)
        //    through the package's policy.globalRoleMapping
        $userId = $user['id'] ?? 0;
        $userSystemRole = $user['role'] ?? 'guest';
        $allowedRoles = $pageConfig['access'] ?? [];

        if (!empty($allowedRoles) && $userSystemRole !== 'super_admin') {
            // Resolve user's package-level role via PackageAccessResolver
            $sectionSlug = $this->getSectionSlug($packageId, $packageData);
            if ($sectionSlug) {
                $resolver = new \Hub\PackageAccessResolver();
                $packageRole = $resolver->getUserPackageRole($userId, $sectionSlug);
                if (!$packageRole || !in_array($packageRole, $allowedRoles)) {
                    return $this->errorResponse(403, 'Access Denied', 'You do not have permission to view this page.');
                }
            }
            // If no section slug found (package not installed via sections), fall through
        }

        // 4. Resolve route parameters (e.g., {student_id} from URL)
        $routeParams = [];
        $routePattern = $pageConfig['route'] ?? '';
        if ($routePattern && $pageId) {
            $routeParams = $this->loader->resolveRouteParams($routePattern, '/' . $pageId) ?? [];
        }

        // Merge route params with request params
        $mergedParams = array_merge($params, $routeParams);

        // 5. Execute queries and render components
        $context = [
            'packageId' => $packageId,
            'pageId' => $pageConfig['id'] ?? $pageId,
            'user' => $user,
            'routeParams' => $routeParams,
            'csrfToken' => $_SESSION['csrf_token'] ?? '',
            'baseUrl' => $this->getPackageBaseUrl($packageData, $packageId),
            'queryParams' => $_GET ?? [],
        ];

        $components = $pageConfig['components'] ?? [];
        $renderedHtml = '';
        $allAssets = ['css' => [], 'js' => []];

        foreach ($components as $component) {
            $type = $component['type'] ?? '';

            if (!$this->components->has($type)) {
                $renderedHtml .= '<div class="alert alert-warning">Unknown component type: ' . e($type) . '</div>';
                continue;
            }

            // Execute query for data-bound components
            $data = [];
            $queryName = $component['dataQuery'] ?? $component['query'] ?? '';
            if ($queryName) {
                $data = $this->executeQuery($packageId, $queryName, $mergedParams, $user, $pageConfig, $packageData);
            }

            // For filters: pre-fetch optionsQuery data for select fields
            if ($type === 'filters') {
                $filterFields = $component['config']['filters'] ?? $component['config']['fields'] ?? $component['filters'] ?? $component['fields'] ?? [];
                foreach ($filterFields as $field) {
                    $optionsQuery = $field['optionsQuery'] ?? '';
                    if ($optionsQuery) {
                        $optionsData = $this->executeQuery($packageId, $optionsQuery, $mergedParams, $user, $pageConfig, $packageData);
                        $data[$optionsQuery] = $optionsData;
                    }
                }
            }

            // Create renderer and render
            // Flatten: if component has a 'config' wrapper, merge it up for the renderer
            $renderConfig = $component;
            if (isset($component['config']) && is_array($component['config'])) {
                $renderConfig = array_merge($component, $component['config']);
            }
            $renderer = $this->components->create($type);
            $renderedHtml .= $renderer->render($renderConfig, $data, $context);

            // Collect assets
            $assets = $renderer->getAssets();
            $allAssets['css'] = array_merge($allAssets['css'], $assets['css'] ?? []);
            $allAssets['js'] = array_merge($allAssets['js'], $assets['js'] ?? []);
        }

        // Deduplicate assets
        $allAssets['css'] = array_unique($allAssets['css']);
        $allAssets['js'] = array_unique($allAssets['js']);

        // Build page title
        $pageTitle = $pageConfig['title'] ?? $packageData['_display_name'] ?? $packageId;
        $pageSubtitle = $pageConfig['subtitle'] ?? '';

        return [
            'status' => 200,
            'title' => $pageTitle,
            'subtitle' => $pageSubtitle,
            'html' => $renderedHtml,
            'assets' => $allAssets,
            'package' => [
                'id' => $packageId,
                'name' => $packageData['package']['display_name'] ?? $packageData['_display_name'] ?? $packageId,
                'icon' => $packageData['_icon'] ?? $packageData['package']['icon'] ?? '',
                'baseUrl' => $context['baseUrl'],
            ],
            'page' => $pageConfig,
            'layout' => $pageConfig['layout'] ?? 'standard',
        ];
    }

    /**
     * Handle an API request for a package query (AJAX)
     *
     * @param string $packageId
     * @param string $queryName
     * @param array $params
     * @param array $user
     * @return array JSON-ready response
     */
    public function handleQuery(string $packageId, string $queryName, array $params, array $user): array
    {
        $packageData = $this->loader->load($packageId);
        if (!$packageData) {
            return ['error' => 'Package not found', 'status' => 404];
        }

        // Find which page this query belongs to (for access check)
        $pageConfig = $this->findPageForQuery($packageData, $queryName);

        $data = $this->executeQuery($packageId, $queryName, $params, $user, $pageConfig, $packageData);

        return array_merge(['status' => 200], $data);
    }

    /**
     * Handle an API request for a package mutation (AJAX)
     *
     * @param string $packageId
     * @param string $mutationName
     * @param array $input
     * @param array $user
     * @return array JSON-ready response
     */
    public function handleMutation(string $packageId, string $mutationName, array $input, array $user): array
    {
        $packageData = $this->loader->load($packageId);
        if (!$packageData) {
            return ['error' => 'Package not found', 'status' => 404];
        }

        // Find which page this mutation belongs to
        $pageConfig = $this->findPageForMutation($packageData, $mutationName);

        if ($this->mutationRouter) {
            $dataClassifications = $this->extractDataClassifications($packageData);
            try {
                $result = $this->mutationRouter->execute(
                    $user,
                    $packageId,
                    $pageConfig ?? ['access' => []],
                    $mutationName,
                    $input,
                    $dataClassifications
                );
                return array_merge(['status' => 200], $result);
            } catch (\Exception $e) {
                return ['error' => $e->getMessage(), 'status' => 400];
            }
        }

        return ['error' => 'Mutation router not configured', 'status' => 500];
    }

    /**
     * Execute a query through the enforcement pipeline or fallback
     */
    private function executeQuery(string $packageId, string $queryName, array $params, array $user, ?array $pageConfig, array $packageData): array
    {
        // Sprint 1: Direct handler dispatch (no enforcement pipeline)
        if (isset($this->directHandlers[$packageId])) {
            $context = [
                'user' => $user,
                'packageId' => $packageId,
                'queryName' => $queryName,
                'pageConfig' => $pageConfig,
            ];
            try {
                return ($this->directHandlers[$packageId])($queryName, $params, $context);
            } catch (\Exception $e) {
                return [
                    'data' => [],
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Sprint 2+: If QueryRouter is available, use enforcement pipeline
        if ($this->queryRouter) {
            $dataClassifications = $this->extractDataClassifications($packageData);
            try {
                return $this->queryRouter->execute(
                    $user,
                    $packageId,
                    $pageConfig ?? ['access' => []],
                    $queryName,
                    $params,
                    $dataClassifications
                );
            } catch (\Exception $e) {
                return [
                    'data' => [],
                    'meta' => ['total' => 0, 'page' => 1, 'perPage' => 50],
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Fallback: direct database query based on package query definition
        return $this->fallbackQuery($packageId, $queryName, $params, $packageData);
    }

    /**
     * Fallback query execution when no handlers are registered
     * Looks at the package's query definitions and attempts a basic DB query
     */
    private function fallbackQuery(string $packageId, string $queryName, array $params, array $packageData): array
    {
        $queries = $packageData['data']['queries'] ?? $packageData['queries'] ?? [];
        $queryDef = $queries[$queryName] ?? null;

        if (!$queryDef) {
            return ['data' => [], 'meta' => ['total' => 0, 'page' => 1, 'perPage' => 50]];
        }

        // For now, return empty data with metadata
        // Real handler implementations will provide actual data
        return [
            'data' => [],
            'meta' => [
                'total' => 0,
                'page' => (int)($params['page'] ?? 1),
                'perPage' => (int)($params['pageSize'] ?? 50),
            ],
            '_queryDef' => $queryDef,
            '_message' => 'No query handler registered. Implement ' . ($queryDef['handler'] ?? 'handler') . ' and register it with HandlerRegistry.',
        ];
    }

    /**
     * Find page config by route/pageId, supporting parameterized routes
     */
    private function findPageByRoute(array $packageData, string $pageId): ?array
    {
        // Support both flat pages array and nested presentation.pages object
        $pages = $packageData['presentation']['pages'] ?? $packageData['pages'] ?? [];

        // If pages is an object (keyed by name), convert to array with id
        $pageList = [];
        foreach ($pages as $key => $page) {
            if (is_string($key) && is_array($page)) {
                $page['id'] = $page['id'] ?? $key;
                $pageList[] = $page;
            } else {
                $pageList[] = $page;
            }
        }
        $pages = $pageList;

        // Direct match by page ID
        foreach ($pages as $page) {
            if (($page['id'] ?? '') === $pageId) {
                return $page;
            }
        }

        // Match by route pattern — two passes:
        // Pass 1: exact static routes only (no {params}), e.g. /vehicle/add
        // Pass 2: parameterized routes, e.g. /vehicle/{id}
        // This ensures /vehicle/add wins over /vehicle/{id} regardless of JSON order
        $parameterizedMatches = [];

        foreach ($pages as $page) {
            $route = trim($page['route'] ?? '', '/');
            $routeParts = array_values(array_filter(explode('/', $route)));
            $pageParts = array_values(array_filter(explode('/', $pageId)));

            if (count($routeParts) !== count($pageParts)) {
                continue;
            }

            $match = true;
            $hasParams = false;
            for ($i = 0; $i < count($routeParts); $i++) {
                if (preg_match('/^\{(\w+)\}$/', $routeParts[$i])) {
                    $hasParams = true;
                    continue; // Parameter slot — always matches
                }
                if ($routeParts[$i] !== $pageParts[$i]) {
                    $match = false;
                    break;
                }
            }

            if ($match) {
                if (!$hasParams) {
                    return $page; // Exact static match — return immediately
                }
                $parameterizedMatches[] = $page; // Defer parameterized matches
            }
        }

        // Return first parameterized match if no static match found
        if (!empty($parameterizedMatches)) {
            return $parameterizedMatches[0];
        }

        // Default to first page if empty pageId
        if (($pageId === '' || $pageId === 'index') && !empty($pages)) {
            return $pages[0];
        }

        return null;
    }

    /**
     * Find the page that uses a given query
     */
    private function findPageForQuery(array $packageData, string $queryName): ?array
    {
        $pages = $packageData['presentation']['pages'] ?? $packageData['pages'] ?? [];
        foreach ($pages as $key => $page) {
            if (is_string($key)) $page['id'] = $page['id'] ?? $key;
            foreach ($page['components'] ?? [] as $component) {
                $qn = $component['dataQuery'] ?? $component['query'] ?? '';
                if ($qn === $queryName) {
                    return $page;
                }
            }
        }
        return null;
    }

    /**
     * Find the page that uses a given mutation
     */
    private function findPageForMutation(array $packageData, string $mutationName): ?array
    {
        $pages = $packageData['presentation']['pages'] ?? $packageData['pages'] ?? [];
        foreach ($pages as $key => $page) {
            if (is_string($key)) $page['id'] = $page['id'] ?? $key;
            foreach ($page['components'] ?? [] as $component) {
                if (($component['mutation'] ?? '') === $mutationName) {
                    return $page;
                }
                foreach ($component['rowActions'] ?? [] as $action) {
                    if (($action['mutation'] ?? '') === $mutationName) {
                        return $page;
                    }
                }
            }
        }
        return null;
    }

    /**
     * Extract data classifications from package definition
     */
    private function extractDataClassifications(array $packageData): array
    {
        $classifications = [];
        $pages = $packageData['presentation']['pages'] ?? $packageData['pages'] ?? [];

        foreach ($pages as $page) {
            foreach ($page['components'] ?? [] as $component) {
                foreach ($component['columns'] ?? [] as $col) {
                    if (isset($col['dataClass'])) {
                        $classifications[$col['key']] = $col['dataClass'];
                    }
                    if (($col['style'] ?? '') === 'masked') {
                        $classifications[$col['key']] = 'confidential';
                    }
                }
            }
        }

        return $classifications;
    }

    /**
     * Get package base URL
     */
    private function getPackageBaseUrl(array $packageData, string $packageId): string
    {
        // Use installed base_url if available
        if (!empty($packageData['_base_url'])) {
            return rtrim($packageData['_base_url'], '/');
        }

        // Use package-defined base_url
        if (!empty($packageData['package']['base_url'])) {
            return rtrim($packageData['package']['base_url'], '/');
        }

        // Default: /p/{packageId}
        return '/p/' . $packageId;
    }

    /**
     * Build error response
     */
    private function errorResponse(int $status, string $title, string $message): array
    {
        $icon = match ($status) {
            403 => 'bi-shield-lock',
            404 => 'bi-question-circle',
            500 => 'bi-exclamation-triangle',
            default => 'bi-info-circle',
        };

        $html = '<div class="pkg-error-state">';
        $html .= '<div class="pkg-error-icon"><i class="bi ' . $icon . '"></i></div>';
        $html .= '<h2>' . e($title) . '</h2>';
        $html .= '<p>' . e($message) . '</p>';
        $html .= '<a href="/hub.php" class="btn btn-primary"><i class="bi bi-house"></i> Back to Hub</a>';
        $html .= '</div>';

        return [
            'status' => $status,
            'title' => $title,
            'subtitle' => '',
            'html' => $html,
            'assets' => ['css' => [], 'js' => []],
            'package' => null,
            'page' => null,
            'layout' => 'error',
        ];
    }

    /**
     * Get the section slug for a package ID.
     * Used to resolve package-level roles via PackageAccessResolver.
     */
    private function getSectionSlug(string $packageId, array $packageData): ?string
    {
        // Check if loader enriched with section data
        $sectionId = $packageData['_section_id'] ?? null;
        if ($sectionId) {
            $db = Database::getInstance();
            $row = $db->fetchOne("SELECT slug FROM sections WHERE id = ?", [$sectionId]);
            if ($row) {
                return $row['slug'];
            }
        }

        // Fallback: look up via section_installations
        $db = Database::getInstance();
        $row = $db->fetchOne(
            "SELECT s.slug FROM section_installations si
             JOIN sections s ON si.section_id = s.id
             WHERE si.package_id = ? AND s.is_active = 1 LIMIT 1",
            [$packageId]
        );
        return $row['slug'] ?? null;
    }
}
