<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Hub\{AuditLogger, Database, Cache};
use Illuminate\Http\{Request, JsonResponse};
use Exception;

/**
 * Package Discovery Controller
 *
 * Handles discovering and downloading packages from GitHub repositories
 *
 * @author The Hub Team
 * @version 2.0.0 (Laravel)
 */
class PackageDiscoveryController extends Controller
{
    const DEFAULT_REPO_OWNER = 'R1CH4RD25';
    const DEFAULT_REPO_NAME = 'TheHub-Package-Repo';
    const CACHE_TTL = 3600; // 1 hour

    /**
     * Search packages in GitHub repository
     */
    public function search(Request $request): JsonResponse
    {
        $currentUser = $request->attributes->get('user');

        // Only super admin and admin can discover packages
        if (!in_array($currentUser['role'], ['super_admin', 'admin'])) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $owner = $request->input('owner', self::DEFAULT_REPO_OWNER);
        $repo = $request->input('repo', self::DEFAULT_REPO_NAME);
        $repositoryUrl = $request->input('repository_url', "https://github.com/{$owner}/{$repo}");
        $bustCache = $request->input('bust_cache', false); // Allow cache busting

        try {
            error_log("Package discovery: Searching {$owner}/{$repo}" . ($bustCache ? ' (cache bust)' : ''));

            $packages = $this->searchGitHubPackages($owner, $repo, $bustCache);

            error_log("Package discovery: Found " . count($packages) . " packages");

            // Check which packages are already installed
            $db = Database::getInstance();
            $installedPackages = $db->fetchAll(
                "SELECT DISTINCT package_id FROM section_installations WHERE status = 'installed'"
            );
            $installedPackageIds = array_column($installedPackages, 'package_id');

            // Check which packages are already downloaded (in section_packages)
            $downloadedPackages = $db->fetchAll(
                "SELECT package_id, version FROM section_packages WHERE package_id IS NOT NULL"
            );
            $downloadedPackageMap = [];
            foreach ($downloadedPackages as $pkg) {
                $key = $pkg['package_id'] . '|' . $pkg['version'];
                $downloadedPackageMap[$key] = true;
            }

            // Mark installed and downloaded status
            foreach ($packages as &$package) {
                $package['is_installed'] = in_array($package['id'], $installedPackageIds);
                $downloadKey = $package['id'] . '|' . $package['version'];
                $package['is_downloaded'] = isset($downloadedPackageMap[$downloadKey]);
            }

            // Log the search action
            AuditLogger::log(
                'package_discovery_search',
                'package_discovery',
                null,
                null,
                [
                    'repository_url' => $repositoryUrl,
                    'packages_found' => count($packages)
                ],
                $currentUser['id']
            );

            return response()->json([
                'success' => true,
                'packages' => $packages,
                'repository' => [
                    'url' => $repositoryUrl,
                    'owner' => $owner,
                    'repo' => $repo
                ]
            ]);
        } catch (Exception $e) {
            error_log("Package discovery error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Download package from repository
     */
    public function download(Request $request): JsonResponse
    {
        $currentUser = $request->attributes->get('user');

        if ($currentUser['role'] !== 'super_admin') {
            return response()->json(['error' => 'Only super admins can download packages'], 403);
        }

        $downloadUrl = $request->input('download_url');
        $packageName = $request->input('package_name');

        if (!$downloadUrl || !$packageName) {
            return response()->json(['error' => 'Download URL and package name required'], 400);
        }

        try {
            $result = $this->downloadPackageFromGitHub($downloadUrl, $packageName, $currentUser['id']);

            // Log the download action
            AuditLogger::log(
                'package_discovery_download',
                'package_discovery',
                null,
                null,
                [
                    'download_url' => $downloadUrl,
                    'package_name' => $packageName,
                    'file_size' => $result['file_size'] ?? 0
                ],
                $currentUser['id']
            );

            // Clear cache
            Cache::delete('packages:installed');

            return response()->json($result);
        } catch (Exception $e) {
            error_log("Package download error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Search GitHub repository for .hubpkg files
     */
    private function searchGitHubPackages(string $owner, string $repo, bool $bustCache = false): array
    {
        // Try cache first
        $cacheKey = "github:packages:{$owner}/{$repo}";
        
        if ($bustCache) {
            error_log("Package discovery: Busting cache for {$owner}/{$repo}");
            Cache::delete($cacheKey);
        } else {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                error_log("Package discovery: Serving " . count($cached) . " packages from cache");
                return $cached;
            }
        }

        $rawPackages = $this->searchGitHubDirectory($owner, $repo, 'packages');

        // Deduplicate by package ID, keeping only the LATEST version.
        // When a new version is published, older .hubpkg files should be moved
        // to packages/archive/ in the GitHub repo. This dedup catches any that weren't.
        $packages = $this->deduplicatePackages($rawPackages);

        error_log("Package discovery: {$owner}/{$repo} — " . count($rawPackages) . " raw → " . count($packages) . " after dedup");

        // Cache for 1 hour
        Cache::set($cacheKey, $packages, self::CACHE_TTL);

        return $packages;
    }

    /**
     * Deduplicate packages by ID, keeping only the latest version.
     * Normalizes IDs to handle cases where metadata fetch fails
     * (e.g., 'district.student-directory' vs 'student-directory').
     */
    private function deduplicatePackages(array $packages): array
    {
        $latestByPackageId = [];
        $nameToCanonicalId = []; // maps normalized names → canonical package_id

        foreach ($packages as $package) {
            $pkgId = $package['id'] ?? '';
            if (empty($pkgId)) {
                continue;
            }

            // Strip category prefix for matching (e.g., 'district.student-directory' → 'student-directory')
            $normalizedName = preg_replace('/^[^.]+\./', '', $pkgId);

            // Also get filename-based name (strip version suffix)
            $fileBaseName = $pkgId;
            if (preg_match('/(.+?)[-_]v?\d+\.\d+/', $pkgId, $m)) {
                $fileBaseName = $m[1];
            }

            // Find canonical ID — check if we've seen this package under a different ID
            $canonicalId = $pkgId;
            if (isset($nameToCanonicalId[$normalizedName])) {
                $canonicalId = $nameToCanonicalId[$normalizedName];
            } elseif (isset($nameToCanonicalId[$fileBaseName])) {
                $canonicalId = $nameToCanonicalId[$fileBaseName];
            } else {
                $nameToCanonicalId[$normalizedName] = $pkgId;
                $nameToCanonicalId[$fileBaseName] = $pkgId;
            }

            if (!isset($latestByPackageId[$canonicalId])) {
                $latestByPackageId[$canonicalId] = $package;
            } else {
                $existingVersion = $latestByPackageId[$canonicalId]['version'] ?? '0.0.0';
                $newVersion = $package['version'] ?? '0.0.0';

                if (version_compare($newVersion, $existingVersion, '>')) {
                    // Newer version — replace
                    error_log("Package dedup '{$canonicalId}': v{$newVersion} replaces v{$existingVersion}");
                    $latestByPackageId[$canonicalId] = $package;
                } elseif ($newVersion === $existingVersion) {
                    // Same version — keep larger file (more complete package)
                    $existingSize = $latestByPackageId[$canonicalId]['size'] ?? 0;
                    $newSize = $package['size'] ?? 0;
                    if ($newSize > $existingSize) {
                        error_log("Package dedup '{$canonicalId}': same v{$newVersion}, keeping larger ({$newSize} > {$existingSize})");
                        $latestByPackageId[$canonicalId] = $package;
                    }
                }
                // else: older version, skip
            }
        }

        return array_values($latestByPackageId);
    }

    /**
     * Recursively search a GitHub directory for packages
     */
    private function searchGitHubDirectory(string $owner, string $repo, string $path = ''): array
    {
        $packages = [];
        $apiUrl = "https://api.github.com/repos/{$owner}/{$repo}/contents" . ($path ? "/{$path}" : '');

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: Hub-Package-Manager/2.0',
                    'Accept: application/vnd.github.v3+json'
                ],
                'timeout' => 30
            ]
        ]);

        try {
            $response = @file_get_contents($apiUrl, false, $context);

            if ($response === false) {
                error_log("GitHub API request failed for path: {$path}");
                return $packages;
            }

            $files = json_decode($response, true);

            if (!is_array($files)) {
                return $packages;
            }

            foreach ($files as $file) {
                // Skip archive directories
                if ($file['type'] === 'dir') {
                    $filePath = $file['path'] ?? '';
                    
                    // Exclude archive folders
                    if (strpos($filePath, 'packages/archive') === 0 || strpos($filePath, '/archive/') !== false) {
                        error_log("Package discovery: Skipping archive path: {$filePath}");
                        continue;
                    }
                    
                    $subPackages = $this->searchGitHubDirectory($owner, $repo, $file['path']);
                    $packages = array_merge($packages, $subPackages);
                }
                // Look for .hubpkg files
                elseif (isset($file['name']) && str_ends_with($file['name'], '.hubpkg')) {
                    $packageInfo = $this->extractPackageInfo($file, $context);
                    if ($packageInfo) {
                        $packages[] = $packageInfo;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error searching GitHub directory {$path}: " . $e->getMessage());
        }

        return $packages;
    }

    /**
     * Extract package information from GitHub file
     */
    private function extractPackageInfo(array $file, $context): ?array
    {
        // Extract tags from directory path
        $tags = [];
        $category = 'other';

        if (preg_match('#^packages/(.+?)/#', $file['path'], $pathMatch)) {
            $pathSegments = explode('/', $pathMatch[1]);
            $tags = array_filter($pathSegments);
            if (!empty($tags)) {
                $category = $tags[0];
            }
        }

        // Default package info
        $packageInfo = [
            'id' => basename($file['name'], '.hubpkg'),
            'name' => basename($file['name'], '.hubpkg'),
            'filename' => $file['name'],
            'download_url' => $file['download_url'],
            'size' => $file['size'] ?? 0,
            'version' => 'Unknown',
            'description' => 'No description available',
            'author' => 'WISD',
            'path' => $file['path'],
            'category' => $category,
            'tags' => $tags
        ];

        // Extract version from filename
        if (preg_match('/(.+?)[-_]v?(\d+\.\d+\.\d+)\.hubpkg$/', $file['name'], $matches)) {
            $packageInfo['name'] = $matches[1];
            $packageInfo['version'] = $matches[2];
            $packageInfo['id'] = $matches[1];
        }

        // Fetch package metadata
        try {
            $packageContent = @file_get_contents($file['download_url'], false, $context);
            if ($packageContent !== false) {
                $packageData = json_decode($packageContent, true);
                if ($packageData && is_array($packageData) && isset($packageData['package'])) {
                    // Override with actual metadata
                    $pkg = $packageData['package'];
                    $packageInfo['id'] = $pkg['id'] ?? $packageInfo['id'];
                    $packageInfo['name'] = $pkg['name'] ?? $packageInfo['name'];
                    $packageInfo['version'] = $pkg['version'] ?? $packageInfo['version'];
                    $packageInfo['description'] = $pkg['description'] ?? $packageInfo['description'];
                    $packageInfo['author'] = $pkg['author'] ?? $packageInfo['author'];
                    $packageInfo['display_name'] = $pkg['display_name'] ?? ucwords(str_replace(['-', '_'], ' ', $packageInfo['name']));
                }
            }
        } catch (Exception $e) {
            error_log("Error fetching package metadata: " . $e->getMessage());
        }

        return $packageInfo;
    }

    /**
     * Download package from GitHub
     */
    private function downloadPackageFromGitHub(string $downloadUrl, string $packageName, int $userId): array
    {
        // Create temp file
        $tempFile = tempnam(sys_get_temp_dir(), 'hubpkg_');

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: Hub-Package-Manager/2.0',
                    'Accept: application/octet-stream'
                ],
                'timeout' => 60
            ]
        ]);

        // Download file
        $packageContent = @file_get_contents($downloadUrl, false, $context);

        if ($packageContent === false) {
            throw new Exception('Failed to download package from GitHub');
        }

        file_put_contents($tempFile, $packageContent);

        // Instead of using uploadPackage, handle the package directly
        // since we're not dealing with an actual HTTP upload
        $packageManager = new \Hub\PackageManager();

        // Generate unique filename
        $filename = uniqid('pkg_') . '.hubpkg';
        $uploadPath = 'uploads/packages/' . $filename;

        // Create upload directory if not exists
        $uploadDir = 'uploads/packages/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        // Copy temp file to upload directory (can't use move_uploaded_file for non-HTTP uploads)
        if (!copy($tempFile, $uploadPath)) {
            @unlink($tempFile);
            throw new Exception('Failed to save package file');
        }

        // Clean up temp file
        @unlink($tempFile);

        // Parse package metadata
        $packageJson = file_get_contents($uploadPath);
        $packageData = json_decode($packageJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            @unlink($uploadPath);
            throw new Exception('Invalid package JSON: ' . json_last_error_msg());
        }

        // Add format_version if missing (for backwards compatibility with older packages)
        if (!isset($packageData['format_version'])) {
            $packageData['format_version'] = '1.0.0';
        }

        // Extract package metadata
        $pkg = $packageData['package'] ?? [];
        $packageId = $pkg['id'] ?? null;
        $version = $pkg['version'] ?? '0.0.0';
        $displayName = $pkg['display_name'] ?? 'Unknown Package';

        if (!$packageId) {
            @unlink($uploadPath);
            throw new Exception('Package missing required ID');
        }

        // Check if package already exists
        $db = Database::getInstance();
        $existing = $db->fetchOne(
            "SELECT id, version FROM section_packages WHERE package_id = ? ORDER BY uploaded_at DESC LIMIT 1",
            [$packageId]
        );

        // Validate package
        $validator = new \Hub\PackageValidator();
        $installType = $existing ? 'upgrade' : 'new';
        $validation = $validator->validate($packageData, $installType);

        if (!$validation['can_install']) {
            @unlink($uploadPath);
            $errors = array_merge(
                $validation['critical_issues'] ?? [],
                $validation['errors'] ?? []
            );
            $errorMessages = array_map(fn($e) => $e['message'] ?? 'Unknown error', $errors);
            throw new Exception('Package validation failed: ' . implode(', ', $errorMessages));
        }

        // Store package record with validation results
        $validationStatus = $validation['overall_status'] ?? 'fail';
        $canInstall = $validation['can_install'] ? 1 : 0;

        // Upsert: if package already exists with this ID, update it (newer version replaces old)
        $pdo = $db->getConnection();
        $stmt = $pdo->prepare(
            "INSERT INTO section_packages (package_id, name, version, display_name, description, author, file_path, uploaded_by, uploaded_at, validation_status, can_install, package_data)
             VALUES (:pid, :name, :ver, :dname, :desc, :author, :fpath, :uid, NOW(), :vstatus, :cinst, :pdata)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                version = VALUES(version),
                display_name = VALUES(display_name),
                description = VALUES(description),
                author = VALUES(author),
                file_path = VALUES(file_path),
                uploaded_by = VALUES(uploaded_by),
                uploaded_at = NOW(),
                validation_status = VALUES(validation_status),
                can_install = VALUES(can_install),
                package_data = VALUES(package_data)"
        );
        $stmt->execute([
            ':pid' => $packageId,
            ':name' => $pkg['name'] ?? $packageId,
            ':ver' => $version,
            ':dname' => $displayName,
            ':desc' => $pkg['description'] ?? '',
            ':author' => $pkg['author'] ?? 'Unknown',
            ':fpath' => $uploadPath,
            ':uid' => $userId,
            ':vstatus' => $validationStatus,
            ':cinst' => $canInstall,
            ':pdata' => json_encode($packageData),
        ]);

        $newPackageRecordId = $pdo->lastInsertId() ?: ($existing['id'] ?? 0);

        // Clear cache
        Cache::delete('packages:installed');

        return [
            'success' => true,
            'package_id' => $newPackageRecordId,
            'package_name' => $displayName,
            'version' => $version,
            'validation_status' => $validationStatus,
            'can_install' => (bool)$canInstall,
            'message' => 'Package downloaded and validated successfully',
            'file_size' => filesize($uploadPath),
            'warnings' => count($validation['warnings'] ?? []),
            'next_step' => 'Package is now available for installation in the Available Packages tab'
        ];
    }
}
