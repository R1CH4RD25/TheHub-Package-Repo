<?php

namespace Hub\Package;

use Hub\Database;

/**
 * Package Loader
 *
 * Loads and caches package definitions from the database or filesystem.
 * Central authority for resolving package JSON, pages, queries, mutations.
 *
 * @see PACKAGE_ARCHITECTURE_SPEC.md §4 (Package JSON Structure)
 */
class PackageLoader
{
    private static array $cache = [];
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Load a package definition by ID
     *
     * @param string $packageId e.g., 'com.woodson.student-directory'
     * @return array|null Package definition or null if not found
     */
    public function load(string $packageId): ?array
    {
        if (isset(self::$cache[$packageId])) {
            return self::$cache[$packageId];
        }

        // Try database first (installed packages)
        $package = $this->loadFromDatabase($packageId);

        // Fallback to filesystem
        if (!$package) {
            $package = $this->loadFromFilesystem($packageId);
        }

        if ($package) {
            self::$cache[$packageId] = $package;
        }

        return $package;
    }

    /**
     * Load package from database (section_packages table)
     */
    private function loadFromDatabase(string $packageId): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT sp.package_data, sp.package_id, si.section_id, s.display_name, s.base_url, s.icon
             FROM section_packages sp
             LEFT JOIN section_installations si ON sp.package_id = si.package_id
             LEFT JOIN sections s ON si.section_id = s.id
             WHERE sp.package_id = ? AND (s.is_active = 1 OR s.is_active IS NULL)
             LIMIT 1",
            [$packageId]
        );

        if (!$row || empty($row['package_data'])) {
            return null;
        }

        $data = json_decode($row['package_data'], true);
        if (!$data) {
            return null;
        }

        // Enrich with installation context
        $data['_installed'] = true;
        $data['_section_id'] = $row['section_id'] ?? null;
        $data['_display_name'] = $row['display_name'] ?? $data['package']['display_name'] ?? $packageId;
        $data['_base_url'] = $row['base_url'] ?? null;
        $data['_icon'] = $row['icon'] ?? null;

        return $data;
    }

    /**
     * Load package from filesystem (packages/ directories)
     *
     * Searches multiple package directories:
     * - packages/local/   — locally developed packages
     * - packages/district/ — district-level packages (student directory, etc.)
     */
    private function loadFromFilesystem(string $packageId): ?array
    {
        $rootPath = dirname(__DIR__, 2) . '/packages/';
        $searchDirs = ['local', 'district'];

        foreach ($searchDirs as $dir) {
            $basePath = $rootPath . $dir . '/';
            if (!is_dir($basePath)) {
                continue;
            }

            // Try direct ID match first
            foreach (['manifest.json', 'package.json'] as $fileName) {
                $path = $basePath . $packageId . '/' . $fileName;
                if (file_exists($path)) {
                    $data = json_decode(file_get_contents($path), true);
                    if ($data) {
                        $data['_installed'] = false;
                        $data['_source'] = 'filesystem';
                        $data['_path'] = dirname($path);
                        return $data;
                    }
                }
            }

            // Search by folder name (id might differ from folder name)
            $folders = glob($basePath . '*', GLOB_ONLYDIR);
            foreach ($folders as $folder) {
                foreach (['manifest.json', 'package.json'] as $fileName) {
                    $path = $folder . '/' . $fileName;
                    if (file_exists($path)) {
                        $data = json_decode(file_get_contents($path), true);
                        if (!$data) continue;
                        $pkgId = $data['package']['id']
                            ?? $data['package']['package_id']
                            ?? $data['id']
                            ?? '';
                        if ($pkgId === $packageId) {
                            $data['_installed'] = false;
                            $data['_source'] = 'filesystem';
                            $data['_path'] = $folder;
                            return $data;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get all installed packages
     *
     * @return array Array of package definitions
     */
    public function getInstalled(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT sp.package_id, sp.package_data, s.display_name, s.base_url, s.icon, s.is_active
             FROM section_packages sp
             LEFT JOIN section_installations si ON sp.package_id = si.package_id
             LEFT JOIN sections s ON si.section_id = s.id
             WHERE s.is_active = 1
             ORDER BY s.display_name"
        );

        $packages = [];
        foreach ($rows as $row) {
            $data = json_decode($row['package_data'] ?? '{}', true);
            if ($data) {
                $data['_installed'] = true;
                $data['_display_name'] = $row['display_name'] ?? $data['package']['display_name'] ?? $row['package_id'];
                $data['_base_url'] = $row['base_url'] ?? null;
                $data['_icon'] = $row['icon'] ?? null;
                $packages[$row['package_id']] = $data;
            }
        }

        return $packages;
    }

    /**
     * Find a page definition within a package
     *
     * @param array $packageData Full package definition
     * @param string $pageId Page identifier from URL
     * @return array|null Page config or null
     */
    public function findPage(array $packageData, string $pageId): ?array
    {
        $pages = $packageData['pages'] ?? [];

        foreach ($pages as $page) {
            $id = $page['id'] ?? '';
            $route = $page['route'] ?? '';

            // Match by ID directly
            if ($id === $pageId) {
                return $page;
            }

            // Match by route slug (last segment of route)
            $routeSlug = trim(basename($route), '/');
            if ($routeSlug === $pageId) {
                return $page;
            }
        }

        // Default: return first page if pageId is empty or 'index'
        if (($pageId === '' || $pageId === 'index') && !empty($pages)) {
            return $pages[0];
        }

        return null;
    }

    /**
     * Resolve route parameters from URL against page route pattern
     * e.g., route="/student-directory/{student_id}" + url="/student-directory/42" => ['student_id' => '42']
     *
     * @param string $routePattern e.g., "/student-directory/{student_id}"
     * @param string $actualPath e.g., "/student-directory/42"
     * @return array|null Matched params or null if no match
     */
    public function resolveRouteParams(string $routePattern, string $actualPath): ?array
    {
        $patternParts = array_values(array_filter(explode('/', $routePattern)));
        $pathParts = array_values(array_filter(explode('/', $actualPath)));

        if (count($patternParts) !== count($pathParts)) {
            return null;
        }

        $params = [];
        for ($i = 0; $i < count($patternParts); $i++) {
            if (preg_match('/^\{(\w+)\}$/', $patternParts[$i], $matches)) {
                $params[$matches[1]] = $pathParts[$i];
            } elseif ($patternParts[$i] !== $pathParts[$i]) {
                return null; // Static segment mismatch
            }
        }

        return $params;
    }

    /**
     * Clear the package cache
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
