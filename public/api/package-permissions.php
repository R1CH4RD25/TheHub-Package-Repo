<?php
/**
 * Package Permissions API
 * 
 * Handles saving role-capability assignments from permission matrix.
 * POST /api/package-permissions.php
 */

require_once __DIR__ . '/../src/bootstrap.php';

use Hub\Auth;
use Hub\PackageCapability;
use Hub\AuditLogger;

header('Content-Type: application/json');

// Require admin login
Auth::requireLogin();
Auth::requireRole(['admin', 'super_admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get JSON payload
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid JSON payload'
            ]);
            exit;
        }
        
        $packageSlug = $data['package'] ?? '';
        $permissions = $data['permissions'] ?? [];
        
        if (empty($packageSlug)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Package slug is required'
            ]);
            exit;
        }
        
        if (!is_array($permissions)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Permissions must be an array'
            ]);
            exit;
        }
        
        // Validate package exists
        $db = \Hub\Database::getInstance()->getConnection();
        $checkPackage = $db->prepare("SELECT name FROM sections WHERE slug = ?");
        $checkPackage->execute([$packageSlug]);
        $package = $checkPackage->fetch(PDO::FETCH_ASSOC);
        
        if (!$package) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Package not found'
            ]);
            exit;
        }
        
        // Save permissions for each role
        $capHelper = new PackageCapability();
        $userId = Auth::user()->id;
        $updatedRoles = 0;
        
        // Get all roles to ensure we clear unassigned ones
        $allRoles = ['staff', 'student', 'maintenance_staff', 'custodial', 'cafeteria', 
                     'custodial_manager', 'maintenance_director', 'business_manager', 
                     'substitute_manager', 'counselor', 'principal', 'admin', 'super_admin'];
        
        foreach ($allRoles as $role) {
            $roleCaps = $permissions[$role] ?? [];
            
            // Set capabilities (empty array clears all)
            $capHelper->setRoleCapabilities($packageSlug, $role, $roleCaps, $userId);
            
            if (!empty($roleCaps)) {
                $updatedRoles++;
            }
        }
        
        // Log the change
        AuditLogger::log('package_permissions_updated', $userId, [
            'package' => $packageSlug,
            'package_name' => $package['name'],
            'updated_roles' => $updatedRoles,
            'total_assignments' => array_sum(array_map('count', $permissions))
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => "Permissions updated for {$updatedRoles} roles",
            'updated_roles' => $updatedRoles
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Server error: ' . $e->getMessage()
        ]);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get current permissions for a package
    $packageSlug = $_GET['package'] ?? '';
    
    if (empty($packageSlug)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Package slug is required'
        ]);
        exit;
    }
    
    try {
        $capHelper = new PackageCapability();
        $capabilities = $capHelper->getPackageCapabilities($packageSlug);
        
        // Get current assignments
        $db = \Hub\Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT role, capability_key
            FROM package_role_capabilities
            WHERE package_slug = ?
            ORDER BY role, capability_key
        ");
        $stmt->execute([$packageSlug]);
        
        $assignments = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($assignments[$row['role']])) {
                $assignments[$row['role']] = [];
            }
            $assignments[$row['role']][] = $row['capability_key'];
        }
        
        echo json_encode([
            'success' => true,
            'package' => $packageSlug,
            'capabilities' => $capabilities,
            'assignments' => $assignments
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Server error: ' . $e->getMessage()
        ]);
    }
    
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
}
