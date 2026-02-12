<?php

/**
 * Package Discovery API
 *
 * Handles discovering and downloading packages from GitHub repositories
 *
 * Endpoints:
 * POST /api/package-discovery.php - Search packages in repository
 * POST /api/package-discovery.php?action=download - Download package from repository
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\AuditLogger;
use Hub\Database;

// Require login
Auth::requireLogin();

// Only super admin and admin can discover packages
$currentUser = Auth::getCurrentUser();
if (!in_array($currentUser['role'], ['super_admin', 'admin'])) {
    jsonResponse(['error' => 'Insufficient permissions'], 403);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    jsonResponse(['error' => 'Invalid JSON input'], 400);
}

// Verify CSRF token from JSON input or headers
$csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    jsonResponse(['error' => 'Invalid CSRF token'], 403);
}

$action = $input['action'] ?? 'search';

// ============================================================================
// Search packages in GitHub repository
// ============================================================================

if ($action === 'search') {
    $repositoryUrl = $input['repository_url'] ?? '';
    $owner = $input['owner'] ?? '';
    $repo = $input['repo'] ?? '';

    if (!$repositoryUrl || !$owner || !$repo) {
        jsonResponse(['error' => 'Repository URL, owner, and repo are required'], 400);
    }

    try {
        error_log("Package discovery: Searching {$owner}/{$repo}");

        $packages = searchGitHubPackages($owner, $repo);

        error_log("Package discovery: Found " . count($packages) . " packages");

        // Check which packages are already installed
        $db = Database::getInstance();
        $installedPackages = $db->fetchAll(
            "SELECT DISTINCT package_id FROM section_installations WHERE package_id IS NOT NULL"
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
        $logger = new AuditLogger();
        $logger->log(
            'package_discovery_search',
            'package_discovery',
            null,
            null,
            [
                'repository_url' => $repositoryUrl,
                'packages_found' => count($packages)
            ]
        );

        jsonResponse([
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
        error_log("Stack trace: " . $e->getTraceAsString());
        jsonResponse(['error' => $e->getMessage()], 500);
    }
}

// ============================================================================
// Download package from repository
// ============================================================================

elseif ($action === 'download') {
    $downloadUrl = $input['download_url'] ?? '';
    $packageName = $input['package_name'] ?? '';

    if (!$downloadUrl || !$packageName) {
        jsonResponse(['error' => 'Download URL and package name are required'], 400);
    }

    try {
        $result = downloadPackageFromGitHub($downloadUrl, $packageName);

        // Log the download action
        $logger = new AuditLogger();
        $logger->log(
            'package_discovery_download',
            'package_discovery',
            null,
            null,
            [
                'download_url' => $downloadUrl,
                'package_name' => $packageName,
                'file_size' => $result['file_size'] ?? 0
            ]
        );

        jsonResponse([
            'success' => true,
            'message' => 'Package downloaded successfully',
            'package' => $result
        ]);
    } catch (Exception $e) {
        error_log("Package download error: " . $e->getMessage());
        jsonResponse(['error' => $e->getMessage()], 500);
    }
} else {
    jsonResponse(['error' => 'Invalid action'], 400);
}

// ============================================================================
// Helper Functions
// ============================================================================

function searchGitHubPackages($owner, $repo)
{
    $packages = [];

    // Search in root directory first
    $packages = array_merge($packages, searchGitHubDirectory($owner, $repo, ''));

    // Search in packages/ subdirectory
    $packages = array_merge($packages, searchGitHubDirectory($owner, $repo, 'packages'));

    // Deduplicate packages by package ID, keeping only the LATEST version.
    // When a package is updated, older versions are automatically hidden.
    // Old .hubpkg files should be moved to packages/archive/ in the GitHub repo.
    //
    // We deduplicate by BOTH:
    // 1. The metadata package_id (e.g., 'district.student-directory')
    // 2. The filename-based name (e.g., 'student-directory') — catches cases
    //    where metadata fetch fails due to GitHub API rate limits
    $latestByPackageId = [];
    $filenameToPackageId = []; // maps filename-based name → canonical package_id

    foreach ($packages as $package) {
        $pkgId = $package['id'] ?? '';
        if (empty($pkgId)) {
            continue;
        }

        // Extract the base name from filename (without version) for secondary matching
        $fileBaseName = $pkgId;
        if (preg_match('/(.+?)[-_]v?\d+\.\d+/', $pkgId, $m)) {
            $fileBaseName = $m[1];
        }
        // Also strip category prefix (e.g., 'district.student-directory' → 'student-directory')
        $normalizedName = preg_replace('/^[^.]+\./', '', $pkgId);

        // Check if we've already seen this package under a different ID
        $canonicalId = $pkgId;
        if (isset($filenameToPackageId[$normalizedName])) {
            $canonicalId = $filenameToPackageId[$normalizedName];
        } elseif (isset($filenameToPackageId[$fileBaseName])) {
            $canonicalId = $filenameToPackageId[$fileBaseName];
        } else {
            // Register both the normalized name and file base name
            $filenameToPackageId[$normalizedName] = $pkgId;
            $filenameToPackageId[$fileBaseName] = $pkgId;
        }

        if (!isset($latestByPackageId[$canonicalId])) {
            // First time seeing this package
            $latestByPackageId[$canonicalId] = $package;
        } else {
            // Compare versions — keep the higher one
            $existingVersion = $latestByPackageId[$canonicalId]['version'] ?? '0.0.0';
            $newVersion = $package['version'] ?? '0.0.0';

            if (version_compare($newVersion, $existingVersion, '>')) {
                error_log("Package '{$canonicalId}': Showing v{$newVersion}, hiding older v{$existingVersion}");
                $latestByPackageId[$canonicalId] = $package;
            } else {
                // Same or older version — keep existing (prefer larger file = more complete)
                $existingSize = $latestByPackageId[$canonicalId]['size'] ?? 0;
                $newSize = $package['size'] ?? 0;
                if ($newVersion === $existingVersion && $newSize > $existingSize) {
                    error_log("Package '{$canonicalId}': Same v{$newVersion}, keeping larger file ({$newSize} > {$existingSize})");
                    $latestByPackageId[$canonicalId] = $package;
                } else {
                    error_log("Package '{$canonicalId}': Keeping v{$existingVersion}, hiding v{$newVersion}");
                }
            }
        }
    }

    return array_values($latestByPackageId);
}

function searchGitHubDirectory($owner, $repo, $path)
{
    $packages = [];

    // GitHub API URL for repository contents
    $apiUrl = "https://api.github.com/repos/{$owner}/{$repo}/contents" . ($path ? "/{$path}" : '');

    // Create a fresh context for each API call to avoid reuse issues
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: Hub-Package-Manager/1.0',
                'Accept: application/vnd.github.v3+json'
            ],
            'timeout' => 30
        ]
    ]);

    // Enable more detailed error reporting
    $prevErrorReporting = error_reporting(E_ALL);

    try {
        $response = @file_get_contents($apiUrl, false, $context);

        if ($response === false) {
            error_log("GitHub API request failed for path: {$path}");
            return $packages; // Return empty array instead of throwing exception for subdirectories
        }

        $files = json_decode($response, true);

        if ($files === null || !is_array($files)) {
            error_log("Invalid JSON response for path: {$path}");
            return $packages;
        }

        foreach ($files as $file) {
            // Skip archive folder at any level (packages/archive/) and hidden directories
            $filename = $file['name'] ?? '';
            $filePath = $file['path'] ?? '';
            
            // Skip if it's the archive directory or inside archive
            if (strpos($filePath, 'packages/archive') === 0 || 
                strpos($filePath, '/archive/') !== false ||
                $filename === 'archive') {
                continue;
            }
            
            // Skip hidden directories and version control
            if ($file['type'] === 'dir' && (
                in_array($filename, ['.git', '.github', 'old-versions']) ||
                strpos($filename, '.') === 0
            )) {
                continue;
            }
            
            // If it's a directory, search recursively (any depth)
            if ($file['type'] === 'dir') {
                $subPackages = searchGitHubDirectory($owner, $repo, $file['path']);
                $packages = array_merge($packages, $subPackages);
            }
            // Look for .hubpkg files
            elseif (isset($file['name']) && substr($file['name'], -7) === '.hubpkg') {
                // Extract tags from directory path
                // Example: packages/student/safety/bullying.hubpkg → tags: ['student', 'safety']
                $tags = [];
                $category = 'other';

                if (preg_match('#^packages/(.+?)/#', $file['path'], $pathMatch)) {
                    // Get all directory segments between 'packages/' and the filename
                    $pathSegments = explode('/', $pathMatch[1]);
                    $tags = array_filter($pathSegments); // Remove empty segments

                    // First segment is the primary category
                    if (!empty($tags)) {
                        $category = $tags[0];
                    }
                }

                // Default package info from filename
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

                // Try to extract version from filename if it follows naming convention
                if (preg_match('/(.+?)[-_]v?(\d+\.\d+\.\d+)\.hubpkg$/', $file['name'], $matches)) {
                    $packageInfo['name'] = $matches[1];
                    $packageInfo['version'] = $matches[2];
                    $packageInfo['id'] = $matches[1];
                }

                // Try to fetch and parse the package file for metadata
                try {
                    $packageContent = @file_get_contents($file['download_url'], false, $context);
                    if ($packageContent !== false) {
                        $packageData = json_decode($packageContent, true);
                        if ($packageData && is_array($packageData)) {
                            // Override with actual package metadata (from nested 'package' object)
                            // CRITICAL: Use the actual package ID from the file for duplicate detection
                            if (isset($packageData['package']['id'])) {
                                $packageInfo['id'] = $packageData['package']['id'];
                            }
                            if (isset($packageData['package']['name'])) {
                                $packageInfo['name'] = $packageData['package']['name'];
                            }
                            if (isset($packageData['package']['version'])) {
                                $packageInfo['version'] = $packageData['package']['version'];
                            }
                            if (isset($packageData['package']['description'])) {
                                $packageInfo['description'] = $packageData['package']['description'];
                            }
                            if (isset($packageData['package']['author'])) {
                                $packageInfo['author'] = $packageData['package']['author'];
                            }
                        }
                    }
                } catch (Exception $e) {
                    // If we can't fetch the file, just use filename metadata
                    error_log("Could not fetch package metadata: " . $e->getMessage());
                }

                $packages[] = $packageInfo;
            }
        }
    } catch (Exception $e) {
        error_log("GitHub directory search error: " . $e->getMessage());
    } finally {
        error_reporting($prevErrorReporting);
    }

    return $packages;
}

function downloadPackageFromGitHub($downloadUrl, $packageName)
{
    // Validate download URL
    if (!filter_var($downloadUrl, FILTER_VALIDATE_URL)) {
        throw new Exception('Invalid download URL');
    }

    // Create uploads directory if it doesn't exist
    $uploadsDir = __DIR__ . '/../../uploads';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }

    // Generate safe filename
    $safePackageName = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $packageName);
    $filename = $safePackageName . '.hubpkg';
    $filePath = $uploadsDir . '/' . $filename;

    // Download the file
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: Hub-Package-Manager/1.0'
            ]
        ]
    ]);

    $fileContent = @file_get_contents($downloadUrl, false, $context);

    if ($fileContent === false) {
        throw new Exception('Failed to download package file');
    }

    // Verify it's valid JSON (hubpkg files are JSON)
    $packageData = json_decode($fileContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Downloaded file is not valid JSON: ' . json_last_error_msg());
    }

    // Verify it has required package structure
    if (!isset($packageData['package']) || !isset($packageData['fields'])) {
        throw new Exception('Downloaded file is not a valid .hubpkg format');
    }

    // Save the file
    if (file_put_contents($filePath, $fileContent) === false) {
        throw new Exception('Failed to save downloaded package');
    }

    // Extract package metadata
    $pkg = $packageData['package'];
    $displayName = $pkg['display_name'] ?? $pkg['name'] ?? $packageName;
    $version = $pkg['version'] ?? 'Unknown';
    $description = $pkg['description'] ?? 'Downloaded from repository';
    $packageId = $pkg['id'] ?? $safePackageName;

    // Insert or update in database (upsert — if a newer version is downloaded, update the existing record)
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $now = date('Y-m-d H:i:s');
    $userId = Auth::getCurrentUser()['id'];
    $fileSize = strlen($fileContent);
    $fileHash = hash('sha256', $fileContent);
    $name = $pkg['name'] ?? $safePackageName;
    $author = $pkg['author'] ?? 'Unknown';
    $license = $pkg['license'] ?? 'Unknown';

    $stmt = $pdo->prepare("
        INSERT INTO section_packages 
            (package_id, name, display_name, version, description, author, license,
             uploaded_by, uploaded_at, file_path, file_size, file_hash, package_data,
             validation_status, can_install)
        VALUES 
            (:package_id, :name, :display_name, :version, :description, :author, :license,
             :uploaded_by, :uploaded_at, :file_path, :file_size, :file_hash, :package_data,
             'pending', 0)
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            display_name = VALUES(display_name),
            version = VALUES(version),
            description = VALUES(description),
            author = VALUES(author),
            license = VALUES(license),
            uploaded_by = VALUES(uploaded_by),
            uploaded_at = VALUES(uploaded_at),
            file_path = VALUES(file_path),
            file_size = VALUES(file_size),
            file_hash = VALUES(file_hash),
            package_data = VALUES(package_data),
            validation_status = 'pending',
            can_install = 0
    ");

    $stmt->execute([
        ':package_id' => $packageId,
        ':name' => $name,
        ':display_name' => $displayName,
        ':version' => $version,
        ':description' => $description,
        ':author' => $author,
        ':license' => $license,
        ':uploaded_by' => $userId,
        ':uploaded_at' => $now,
        ':file_path' => $filePath,
        ':file_size' => $fileSize,
        ':file_hash' => $fileHash,
        ':package_data' => $fileContent
    ]);

    $dbPackageId = $pdo->lastInsertId() ?: $db->fetchOne(
        "SELECT id FROM section_packages WHERE package_id = ?", [$packageId]
    )['id'];

    return [
        'id' => $dbPackageId,
        'package_id' => $packageId,
        'name' => $pkg['name'] ?? $safePackageName,
        'display_name' => $displayName,
        'version' => $version,
        'file_path' => $filePath,
        'file_size' => strlen($fileContent)
    ];
}
