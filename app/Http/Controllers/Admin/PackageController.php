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
    public function index(Request $request, string $activeTab = 'available')
    {
        $currentUser = $request->attributes->get('user');
        $isSuperAdmin = ($currentUser['role'] === 'super_admin');

        return view('admin.packages', [
            'isSuperAdmin' => $isSuperAdmin,
            'activeTab' => $activeTab,
        ]);
    }

    /**
     * Display available packages.
     */
    public function available(Request $request)
    {
        return $this->index($request, 'available');
    }

    /**
     * Display installed packages.
     */
    public function installed(Request $request)
    {
        return $this->index($request, 'installed');
    }

    /**
     * Display package updates.
     */
    public function updates(Request $request)
    {
        return $this->index($request, 'updates');
    }

    /**
     * Display package creator wizard.
     */
    public function create(Request $request)
    {
        $currentUser = $request->attributes->get('user');
        $isSuperAdmin = ($currentUser['role'] === 'super_admin');

        return view('admin.packages-create', [
            'isSuperAdmin' => $isSuperAdmin,
        ]);
    }

    /**
     * Display configuration for a specific package.
     */
    public function configurePackage(Request $request, string $packageId)
    {
        $currentUser = $request->attributes->get('user');
        $isSuperAdmin = ($currentUser['role'] === 'super_admin');

        return view('admin.packages-configure', [
            'isSuperAdmin' => $isSuperAdmin,
            'packageId' => $packageId
        ]);
    }

    /**
     * Display package configuration interface.
     */
    public function configure(Request $request)
    {
        $currentUser = $request->attributes->get('user');
        $isSuperAdmin = ($currentUser['role'] === 'super_admin');

        return view('admin.packages-configure', [
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
            $fresh = $request->query('fresh') === '1';

            if ($type === 'installed') {
                $packages = $packageManager->getInstalledPackages($fresh);
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
                    ->whereIn('validation_status', ['validated', 'pass', 'warning'])
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
     * Get record count for a package (used by uninstall confirmation modal).
     */
    public function recordCount(Request $request, string $packageId): JsonResponse
    {
        try {
            $db = \Hub\Database::getInstance();
            $installation = $db->fetchOne(
                "SELECT si.section_id, s.display_name FROM section_installations si
                 JOIN sections s ON si.section_id = s.id
                 WHERE si.package_id = ?",
                [$packageId]
            );

            if (!$installation) {
                return response()->json(['record_count' => 0, 'display_name' => 'Unknown']);
            }

            $count = $db->fetchOne(
                "SELECT COUNT(*) as cnt FROM section_records WHERE section_id = ?",
                [$installation['section_id']]
            );

            return response()->json([
                'record_count' => (int) ($count['cnt'] ?? 0),
                'display_name' => $installation['display_name'] ?? 'Unknown'
            ]);
        } catch (\Exception $e) {
            return response()->json(['record_count' => 0, 'display_name' => 'Unknown']);
        }
    }

    /**
     * Upgrade an installed package to the latest available version.
     */
    public function upgrade(Request $request, string $packageId): JsonResponse
    {
        $currentUser = $request->attributes->get('user');

        try {
            $db = \Hub\Database::getInstance();

            // Find the latest package record for this package_id
            $packageRecord = $db->fetchOne(
                "SELECT id FROM section_packages WHERE package_id = ? ORDER BY uploaded_at DESC LIMIT 1",
                [$packageId]
            );

            if (!$packageRecord) {
                return response()->json(['success' => false, 'error' => 'Package not found'], 404);
            }

            $packageManager = new PackageManager();
            $result = $packageManager->upgradePackage((int) $packageRecord['id'], $currentUser['id']);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Upgrade failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Uninstall a package.
     */
    public function uninstall(Request $request, string $packageId): JsonResponse
    {
        $currentUser = $request->attributes->get('user');

        try {
            $keepData = filter_var(
                $request->input('keep_data', false),
                FILTER_VALIDATE_BOOLEAN
            );

            $packageManager = new PackageManager();
            $result = $packageManager->uninstallPackage($packageId, $currentUser['id'], $keepData);

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
     * Get configuration data for a package (roles, mappings, org_roles, settings).
     */
    public function getConfigData(Request $request, int $sectionId): JsonResponse
    {
        try {
            $db = \Hub\Database::getInstance();

            // Get section + installation info
            $section = $db->fetchOne(
                "SELECT s.*, si.installed_version, si.package_id as pkg_id, si.status as install_status
                 FROM sections s
                 JOIN section_installations si ON si.section_id = s.id
                 WHERE s.id = ? AND si.status = 'installed'",
                [$sectionId]
            );

            if (!$section) {
                return response()->json(['error' => 'Installed package not found'], 404);
            }

            // Get package-defined roles
            $packageRoles = $db->fetchAll(
                "SELECT id, role_key, role_name, tier_level, description, permissions
                 FROM package_roles WHERE package_id = ? ORDER BY tier_level ASC",
                [$sectionId]
            );

            // Decode permissions JSON for each role
            foreach ($packageRoles as &$role) {
                $role['permissions'] = json_decode($role['permissions'], true) ?? [];
            }

            // Get current role mappings
            $roleMappings = $db->fetchAll(
                "SELECT prm.id, prm.package_role_id, prm.org_role_id,
                        pr.role_key, pr.role_name,
                        org.name as org_role_name
                 FROM package_role_mappings prm
                 JOIN package_roles pr ON prm.package_role_id = pr.id
                 JOIN org_roles org ON prm.org_role_id = org.id
                 WHERE prm.package_id = ?",
                [$sectionId]
            );

            // Get all available org_roles
            $orgRoles = $db->fetchAll(
                "SELECT id, name, description FROM org_roles WHERE is_active = 1 ORDER BY name"
            );

            // Get section_role_access (legacy ENUM roles)
            $sectionRoles = $db->fetchAll(
                "SELECT role FROM section_role_access WHERE section_id = ?",
                [$sectionId]
            );

            // Get capabilities if v3
            $capabilities = null;
            if ($section['supports_capabilities'] && $section['capabilities_json']) {
                $capabilities = json_decode($section['capabilities_json'], true);
            }

            return response()->json([
                'success' => true,
                'section' => [
                    'id' => $section['id'],
                    'display_name' => $section['display_name'],
                    'slug' => $section['slug'],
                    'description' => $section['description'],
                    'icon' => $section['icon'],
                    'base_url' => $section['base_url'],
                    'is_active' => (bool) $section['is_active'],
                    'installed_version' => $section['installed_version'],
                    'package_id' => $section['pkg_id'],
                ],
                'packageRoles' => $packageRoles,
                'roleMappings' => $roleMappings,
                'orgRoles' => $orgRoles,
                'sectionRoles' => array_column($sectionRoles, 'role'),
                'capabilities' => $capabilities,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to load config: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update role mappings for a package.
     */
    public function updateRoleMappings(Request $request, int $sectionId): JsonResponse
    {
        $currentUser = $request->attributes->get('user');
        if ($currentUser['role'] !== 'super_admin') {
            return response()->json(['error' => 'Only super admins can configure packages'], 403);
        }

        try {
            $db = \Hub\Database::getInstance();
            $mappings = $request->input('mappings', []); // [{package_role_id, org_role_id}, ...]

            // Clear existing mappings for this package
            $db->execute("DELETE FROM package_role_mappings WHERE package_id = ?", [$sectionId]);

            // Insert new mappings
            $created = 0;
            foreach ($mappings as $mapping) {
                if (empty($mapping['package_role_id']) || empty($mapping['org_role_id'])) continue;
                $db->insert('package_role_mappings', [
                    'package_id' => $sectionId,
                    'package_role_id' => (int) $mapping['package_role_id'],
                    'org_role_id' => (int) $mapping['org_role_id'],
                    'mapped_by' => $currentUser['id'],
                    'mapped_at' => date('Y-m-d H:i:s'),
                ]);
                $created++;
            }

            AuditLogger::logUpdate(
                'package_role_mappings',
                $sectionId,
                [],
                ['mappings_count' => $created],
                $currentUser['id']
            );

            return response()->json([
                'success' => true,
                'message' => "Updated {$created} role mapping(s)",
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update mappings: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Toggle package active/inactive status.
     */
    public function toggleActive(Request $request, int $sectionId): JsonResponse
    {
        $currentUser = $request->attributes->get('user');
        if ($currentUser['role'] !== 'super_admin') {
            return response()->json(['error' => 'Only super admins can toggle packages'], 403);
        }

        try {
            $db = \Hub\Database::getInstance();
            $section = $db->fetchOne("SELECT id, is_active, display_name FROM sections WHERE id = ?", [$sectionId]);
            if (!$section) {
                return response()->json(['error' => 'Section not found'], 404);
            }

            $newStatus = $section['is_active'] ? 0 : 1;
            $db->execute("UPDATE sections SET is_active = ? WHERE id = ?", [$newStatus, $sectionId]);

            AuditLogger::logUpdate(
                'sections',
                $sectionId,
                ['is_active' => $section['is_active']],
                ['is_active' => $newStatus],
                $currentUser['id']
            );

            return response()->json([
                'success' => true,
                'is_active' => (bool) $newStatus,
                'message' => $section['display_name'] . ($newStatus ? ' enabled' : ' disabled'),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to toggle status: ' . $e->getMessage()], 500);
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
}
