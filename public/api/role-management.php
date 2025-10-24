<?php
/**
 * Role Management API
 * Super Admin only - manage which roles are active/inactive
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\Database;
use Hub\Roles;
use Hub\AuditLogger;

header('Content-Type: application/json');

// Require super admin
Auth::requireLogin();
Auth::requireRole(['super_admin']);

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetRoles($db);
            break;
            
        case 'POST':
        case 'PUT':
            handleUpdateRole($db);
            break;
            
        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (\Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}

/**
 * Get all roles with their active status
 */
function handleGetRoles($db) {
    // Get all roles from Roles.php
    $allRoles = Roles::getOrdered();
    
    // Get role settings from database
    $stmt = $db->query("SELECT * FROM role_settings ORDER BY display_order");
    $settings = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    // Create a map of settings by role_value
    $settingsMap = [];
    foreach ($settings as $setting) {
        $settingsMap[$setting['role_value']] = $setting;
    }
    
    // Merge role definitions with settings
    $rolesWithSettings = [];
    foreach ($allRoles as $role) {
        $setting = $settingsMap[$role['value']] ?? null;
        
        $rolesWithSettings[] = [
            'value' => $role['value'],
            'label' => $role['label'],
            'description' => $role['description'],
            'hierarchy' => $role['hierarchy'],
            'is_active' => $setting ? (bool)$setting['is_active'] : true,
            'display_order' => $setting ? $setting['display_order'] : 0,
            'notes' => $setting['notes'] ?? null,
            'can_disable' => !in_array($role['value'], ['super_admin', 'staff']) // Can't disable these core roles
        ];
    }
    
    // Get usage stats for each role
    $usageStmt = $db->query("
        SELECT role, COUNT(*) as user_count 
        FROM users 
        WHERE is_active = TRUE
        GROUP BY role
    ");
    $usageMap = [];
    while ($row = $usageStmt->fetch()) {
        $usageMap[$row['role']] = (int)$row['user_count'];
    }
    
    // Add usage stats
    foreach ($rolesWithSettings as &$role) {
        $role['active_users'] = $usageMap[$role['value']] ?? 0;
    }
    
    jsonResponse([
        'success' => true,
        'roles' => $rolesWithSettings
    ]);
}

/**
 * Update role status (active/inactive) or settings
 */
function handleUpdateRole($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['role_value'])) {
        jsonResponse(['error' => 'role_value is required'], 400);
    }
    
    $roleValue = $data['role_value'];
    
    // Prevent disabling critical roles
    if (in_array($roleValue, ['super_admin', 'staff']) && isset($data['is_active']) && !$data['is_active']) {
        jsonResponse(['error' => 'Cannot disable super_admin or staff roles'], 400);
    }
    
    // Check if role setting exists
    $stmt = $db->prepare("SELECT id FROM role_settings WHERE role_value = :role");
    $stmt->execute(['role' => $roleValue]);
    $exists = $stmt->fetch();
    
    $currentUser = Auth::getCurrentUser();
    
    if ($exists) {
        // Update existing
        $updateFields = [];
        $params = ['role' => $roleValue];
        
        if (isset($data['is_active'])) {
            $updateFields[] = 'is_active = :is_active';
            $params['is_active'] = $data['is_active'] ? 1 : 0;
            
            if (!$data['is_active']) {
                $updateFields[] = 'disabled_at = NOW()';
                $updateFields[] = 'disabled_by = :disabled_by';
                $params['disabled_by'] = $currentUser['id'];
            } else {
                $updateFields[] = 'disabled_at = NULL';
                $updateFields[] = 'disabled_by = NULL';
            }
        }
        
        if (isset($data['display_order'])) {
            $updateFields[] = 'display_order = :display_order';
            $params['display_order'] = (int)$data['display_order'];
        }
        
        if (isset($data['notes'])) {
            $updateFields[] = 'notes = :notes';
            $params['notes'] = $data['notes'];
        }
        
        if (!empty($updateFields)) {
            $sql = "UPDATE role_settings SET " . implode(', ', $updateFields) . " WHERE role_value = :role";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        }
    } else {
        // Insert new
        $stmt = $db->prepare("
            INSERT INTO role_settings (role_value, is_active, display_order, notes, disabled_by)
            VALUES (:role, :is_active, :display_order, :notes, :disabled_by)
        ");
        $stmt->execute([
            'role' => $roleValue,
            'is_active' => $data['is_active'] ?? 1,
            'display_order' => $data['display_order'] ?? 0,
            'notes' => $data['notes'] ?? null,
            'disabled_by' => (!($data['is_active'] ?? true)) ? $currentUser['id'] : null
        ]);
    }
    
    // Audit log
    AuditLogger::log(
        'role_settings_updated',
        'role_settings',
        null,
        null,
        $currentUser['id'],
        [
            'role' => $roleValue,
            'changes' => $data
        ]
    );
    
    jsonResponse([
        'success' => true,
        'message' => 'Role settings updated successfully'
    ]);
}
