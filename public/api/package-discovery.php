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
        
        // Mark installed status
        foreach ($packages as &$package) {
            $package['is_installed'] = in_array($package['id'], $installedPackageIds);
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
}

else {
    jsonResponse(['error' => 'Invalid action'], 400);
}

// ============================================================================
// Helper Functions
// ============================================================================

function searchGitHubPackages($owner, $repo) {
    $packages = [];
    
    // Set up context for GitHub API request
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
    
    // Search in root directory first
    $packages = array_merge($packages, searchGitHubDirectory($owner, $repo, '', $context));
    
    // Search in packages/ subdirectory
    $packages = array_merge($packages, searchGitHubDirectory($owner, $repo, 'packages', $context));
    
    return $packages;
}

function searchGitHubDirectory($owner, $repo, $path, $context) {
    $packages = [];
    
    // GitHub API URL for repository contents
    $apiUrl = "https://api.github.com/repos/{$owner}/{$repo}/contents" . ($path ? "/{$path}" : '');
    
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
            // If it's a directory and we're at packages/ level, search recursively
            if ($file['type'] === 'dir' && $path === 'packages') {
                $subPackages = searchGitHubDirectory($owner, $repo, $file['path'], $context);
                $packages = array_merge($packages, $subPackages);
            }
            // Look for .hubpkg files
            elseif (isset($file['name']) && substr($file['name'], -7) === '.hubpkg') {
                $packageInfo = [
                    'id' => basename($file['name'], '.hubpkg'),
                    'name' => basename($file['name'], '.hubpkg'),
                    'filename' => $file['name'],
                    'download_url' => $file['download_url'],
                    'size' => $file['size'] ?? 0,
                    'version' => 'Unknown',
                    'description' => 'Package from ' . $owner . '/' . $repo,
                    'path' => $file['path']
                ];
                
                // Try to extract version from filename if it follows naming convention
                if (preg_match('/(.+?)[-_]v?(\d+\.\d+\.\d+)\.hubpkg$/', $file['name'], $matches)) {
                    $packageInfo['name'] = $matches[1];
                    $packageInfo['version'] = $matches[2];
                    $packageInfo['id'] = $matches[1];
                }
                
                $packages[] = $packageInfo;
            }
        }
        
        return $packages;
        
    } finally {
        error_reporting($prevErrorReporting);
    }
}

function downloadPackageFromGitHub($downloadUrl, $packageName) {
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
    
    // Verify it's a valid zip file (basic check)
    if (substr($fileContent, 0, 4) !== "PK\x03\x04" && substr($fileContent, 0, 4) !== "PK\x05\x06") {
        throw new Exception('Downloaded file is not a valid package format');
    }
    
    // Save the file
    if (file_put_contents($filePath, $fileContent) === false) {
        throw new Exception('Failed to save downloaded package');
    }
    
    // Insert into database
    $db = Database::getInstance();
    
    $packageId = $db->insert('section_packages', [
        'package_id' => $safePackageName,
        'display_name' => $packageName,
        'version' => 'Unknown', // Will be updated during validation
        'description' => 'Downloaded from repository',
        'uploaded_by' => Auth::getCurrentUser()['id'],
        'uploaded_at' => date('Y-m-d H:i:s'),
        'file_path' => $filePath,
        'file_size' => strlen($fileContent),
        'file_hash' => hash('sha256', $fileContent),
        'validation_status' => 'pending',
        'can_install' => 0
    ]);
    
    return [
        'id' => $packageId,
        'package_id' => $safePackageName,
        'display_name' => $packageName,
        'file_path' => $filePath,
        'file_size' => strlen($fileContent)
    ];
}