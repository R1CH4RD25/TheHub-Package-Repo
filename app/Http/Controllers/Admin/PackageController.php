<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PackageInstall;
use Hub\{Auth as LegacyAuth, AuditLogger, PackageManager, PackageValidator};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PackageController extends Controller
{
    /**
     * Display package management interface.
     */
    public function index(Request $request)
    {
        $currentUser = $request->attributes->get('user');
        $isSuperAdmin = ($currentUser['role'] === 'super_admin');

        return view('admin.packages', [
            'isSuperAdmin' => $isSuperAdmin
        ]);
    }

    /**
     * List packages (installed or available).
     */
    public function list(Request $request): JsonResponse
    {
        $type = $request->query('type', 'installed'); // 'installed', 'available', 'updates'

        $packageManager = new PackageManager();

        try {
            if ($type === 'installed') {
                $packages = $packageManager->getInstalledPackages();
            } elseif ($type === 'updates') {
                $packages = $packageManager->checkForUpdates();
            } else {
                // Available packages (uploaded but not installed) - exclude already installed
                $installedPackageIds = \DB::table('section_installations')
                    ->where('status', 'installed')
                    ->pluck('package_id')
                    ->toArray();

                $packages = Package::whereNotIn('package_id', $installedPackageIds)
                    ->where('can_install', 1)
                    ->whereIn('validation_status', ['validated', 'pass'])
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->toArray();
            }

            return response()->json([
                'success' => true,
                'packages' => $packages
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load packages: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload a package file.
     */
    public function upload(Request $request): JsonResponse
    {
        $currentUser = $request->attributes->get('user');

        if ($currentUser['role'] !== 'super_admin') {
            return response()->json(['error' => 'Only super admins can upload packages'], 403);
        }

        if (!$request->hasFile('package')) {
            return response()->json(['error' => 'No package file provided'], 400);
        }

        $file = $request->file('package');

        if (!$file->isValid()) {
            return response()->json(['error' => 'File upload failed'], 400);
        }

        if ($file->getSize() > 50 * 1024 * 1024) { // 50MB
            return response()->json(['error' => 'File size exceeds 50MB limit'], 400);
        }

        try {
            $packageManager = new PackageManager();
            $result = $packageManager->uploadPackage($file->getRealPath(), $file->getClientOriginalName());

            if ($result['success']) {
                AuditLogger::logCreate(
                    'section_packages',
                    $result['package_id'],
                    null,
                    ['filename' => $file->getClientOriginalName()],
                    $currentUser['id']
                );
            }

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Upload failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Install a package.
     */
    public function install(Request $request, int $id): JsonResponse
    {
        $currentUser = $request->attributes->get('user');

        try {
            $packageManager = new PackageManager();
            $result = $packageManager->installPackage($id, $currentUser['id']);

            // Audit logging already handled in PackageManager->installPackage()

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Installation failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Uninstall a package.
     */
    public function uninstall(Request $request, string $packageId): JsonResponse
    {
        $currentUser = $request->attributes->get('user');

        try {
            $packageManager = new PackageManager();
            $result = $packageManager->uninstallPackage($packageId, $currentUser['id']);

            // Audit logging already handled in PackageManager->uninstallPackage()

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Uninstall failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete an uploaded package (before installation).
     */
    public function delete(Request $request, int $id): JsonResponse
    {
        $currentUser = $request->attributes->get('user');

        if ($currentUser['role'] !== 'super_admin') {
            return response()->json(['error' => 'Only super admins can delete packages'], 403);
        }

        try {
            $package = Package::find($id);

            if (!$package) {
                return response()->json(['error' => 'Package not found'], 404);
            }

            $oldData = $package->toArray();
            $package->delete();

            AuditLogger::logDelete(
                'section_packages',
                $id,
                $oldData,
                $currentUser['id']
            );

            return response()->json(['success' => true, 'message' => 'Package deleted']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Delete failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get validation results for a package.
     */
    public function validation(Request $request, int $id): JsonResponse
    {
        try {
            $package = Package::find($id);

            if (!$package) {
                return response()->json(['error' => 'Package not found'], 404);
            }

            $packageManager = new PackageManager();
            $validation = $packageManager->getValidationResults($id);

            return response()->json([
                'success' => true,
                'package' => $package->toArray(),
                'validation' => $validation
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Validation check failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Search GitHub repository for available packages.
     */
    public function discoverySearch(Request $request): JsonResponse
    {
        $owner = $request->input('owner', 'R1CH4RD25');
        $repo = $request->input('repo', 'TheHub-Package-Repo');
        
        try {
            $packages = [];
            
            // Recursively search all directories in the repository
            $this->searchGitHubDirectory($owner, $repo, 'packages', $packages);
            
            return response()->json([
                'success' => true,
                'packages' => $packages
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'packages' => []
            ]);
        }
    }
    
    /**
     * Recursively search GitHub directory for .hubpkg files
     */
    private function searchGitHubDirectory(string $owner, string $repo, string $path, array &$packages): void
    {
        $url = "https://api.github.com/repos/{$owner}/{$repo}/contents/{$path}";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'User-Agent: TheHub-Package-Manager',
                'Accept: application/vnd.github.v3+json'
            ],
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return;
        }
        
        $items = json_decode($response, true);
        
        if (!is_array($items)) {
            return;
        }
        
        foreach ($items as $item) {
            if ($item['type'] === 'file' && str_ends_with($item['name'], '.hubpkg')) {
                // Download and parse the package file to get metadata
                $packageData = $this->downloadAndParsePackage($item['download_url']);
                
                if ($packageData) {
                    $pathParts = explode('/', $item['path']);
                    $category = $pathParts[1] ?? 'other';
                    
                    $packages[] = [
                        'name' => $item['name'],
                        'display_name' => $packageData['display_name'] ?? $packageData['name'] ?? str_replace('.hubpkg', '', $item['name']),
                        'version' => $packageData['version'] ?? 'unknown',
                        'author' => $packageData['author'] ?? 'unknown',
                        'description' => $packageData['description'] ?? '',
                        'category' => $category,
                        'download_url' => $item['download_url'],
                        'size' => $item['size'],
                        'sha' => $item['sha'],
                        'path' => $item['path'],
                        'package_id' => $packageData['package_id'] ?? null
                    ];
                }
            } elseif ($item['type'] === 'dir') {
                // Recursively search subdirectories
                $this->searchGitHubDirectory($owner, $repo, $item['path'], $packages);
            }
        }
    }
    
    /**
     * Download and parse a .hubpkg file from GitHub
     */
    private function downloadAndParsePackage(string $downloadUrl): ?array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $downloadUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'User-Agent: TheHub-Package-Manager'
            ],
            CURLOPT_TIMEOUT => 15
        ]);
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$content) {
            return null;
        }
        
        $data = json_decode($content, true);
        
        if (!$data || !isset($data['package'])) {
            return null;
        }
        
        return $data['package'];
    }
}
