<?php

/**
 * Organization Roles API
 * Manage custom organization roles and user assignments
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\Database;
use Hub\OrgRole;
use Hub\AuditLogger;

header('Content-Type: application/json');

// Require admin access
Auth::requireLogin();
Auth::requireRole(['super_admin', 'admin']);

$db = Database::getInstance();
$orgRole = new OrgRole();
$method = $_SERVER['REQUEST_METHOD'];

try {
    $action = $_GET['action'] ?? null;

    switch ($method) {
        case 'GET':
            handleGet($orgRole, $action);
            break;

        case 'POST':
            handlePost($orgRole, $action);
            break;

        case 'PUT':
            handlePut($orgRole);
            break;

        case 'DELETE':
            handleDelete($orgRole);
            break;

        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (\Exception $e) {
    error_log("Org Roles API Error: " . $e->getMessage());
    jsonResponse(['error' => $e->getMessage()], 500);
}

/**
 * GET handlers
 */
function handleGet($orgRole, $action)
{
    switch ($action) {
        case 'list':
            getRolesList($orgRole);
            break;

        case 'users':
            getRoleUsers($orgRole);
            break;

        case 'google-groups':
            getGoogleGroupMappings($orgRole);
            break;

        case 'microsoft-groups':
            getMicrosoftGroupMappings($orgRole);
            break;

        default:
            getRolesList($orgRole);
    }
}

/**
 * Get all organization roles with user counts
 */
function getRolesList($orgRole)
{
    $roles = $orgRole->getAll();

    // Add user counts, Google Groups, and Microsoft Groups info
    foreach ($roles as &$role) {
        $role['user_count'] = $orgRole->getRoleUserCount($role['id']);

        $db = Database::getInstance()->getConnection();

        // Get Google Group mappings
        $stmt = $db->prepare("SELECT google_group_email FROM org_role_google_groups WHERE org_role_id = :role_id AND is_active = 1");
        $stmt->execute(['role_id' => $role['id']]);
        $role['google_groups'] = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        // Get Microsoft Group mappings
        $stmt = $db->prepare("SELECT id, azure_group_id, azure_group_name FROM org_role_microsoft_groups WHERE org_role_id = :role_id AND is_active = 1");
        $stmt->execute(['role_id' => $role['id']]);
        $role['microsoft_groups'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    jsonResponse([
        'success' => true,
        'roles' => $roles
    ]);
}

/**
 * Get users assigned to a specific role
 */
function getRoleUsers($orgRole)
{
    if (empty($_GET['role_id'])) {
        jsonResponse(['error' => 'role_id required'], 400);
    }

    $roleId = (int)$_GET['role_id'];
    $userIds = $orgRole->getUsersByRole($roleId);

    // Get user details
    if (empty($userIds)) {
        jsonResponse(['success' => true, 'users' => []]);
        return;
    }

    $db = Database::getInstance()->getConnection();
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $stmt = $db->prepare("SELECT id, name, email, picture FROM users WHERE id IN ($placeholders) AND is_active = 1 ORDER BY name");
    $stmt->execute($userIds);

    jsonResponse([
        'success' => true,
        'users' => $stmt->fetchAll(\PDO::FETCH_ASSOC)
    ]);
}

/**
 * Get Google Group mappings for all roles
 */
function getGoogleGroupMappings($orgRole)
{
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("
        SELECT orgg.*, or.name as role_name
        FROM org_role_google_groups orgg
        JOIN org_roles or ON orgg.org_role_id = or.id
        WHERE orgg.is_active = 1
        ORDER BY or.name, orgg.google_group_email
    ");

    jsonResponse([
        'success' => true,
        'mappings' => $stmt->fetchAll(\PDO::FETCH_ASSOC)
    ]);
}

/**
 * POST handlers
 */
function handlePost($orgRole, $action)
{
    $data = json_decode(file_get_contents('php://input'), true);

    switch ($action) {
        case 'create':
            createRole($orgRole, $data);
            break;

        case 'assign-users':
            assignUsers($orgRole, $data);
            break;

        case 'add-google-group':
            addGoogleGroup($orgRole, $data);
            break;

        case 'sync-google-groups':
            syncGoogleGroups($orgRole);
            break;

        case 'add-microsoft-group':
            addMicrosoftGroup($orgRole, $data);
            break;

        case 'sync-microsoft-groups':
            syncMicrosoftGroups($orgRole);
            break;

        default:
            createRole($orgRole, $data);
    }
}

/**
 * Create a new organization role
 */
function createRole($orgRole, $data)
{
    if (empty($data['name'])) {
        jsonResponse(['error' => 'Role name is required'], 400);
    }

    $currentUser = Auth::getCurrentUser();
    $roleId = $orgRole->create($data['name'], $data['description'] ?? null);

    // Log the creation
    AuditLogger::log('org_role_created', 'org_roles', $roleId, null, [
        'name' => $data['name'],
        'description' => $data['description'] ?? null
    ]);

    jsonResponse([
        'success' => true,
        'role_id' => $roleId,
        'message' => 'Organization role created successfully'
    ]);
}

/**
 * Assign users to a role (bulk operation)
 */
function assignUsers($orgRole, $data)
{
    if (empty($data['role_id']) || !isset($data['user_ids'])) {
        jsonResponse(['error' => 'role_id and user_ids required'], 400);
    }

    $roleId = (int)$data['role_id'];
    $userIds = (array)$data['user_ids'];
    $currentUser = Auth::getCurrentUser();

    // Get current assignments
    $currentUserIds = $orgRole->getUsersByRole($roleId);

    // Add new assignments
    $added = [];
    foreach ($userIds as $userId) {
        if (!in_array($userId, $currentUserIds)) {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("INSERT INTO user_org_roles (user_id, org_role_id, assigned_by) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $roleId, $currentUser['id']]);
            $added[] = $userId;
        }
    }

    // Log the assignments
    if (!empty($added)) {
        $role = $orgRole->getById($roleId);
        AuditLogger::log('org_role_users_assigned', 'org_roles', $roleId, null, [
            'role_name' => $role['name'],
            'user_ids' => $added,
            'count' => count($added)
        ]);
    }

    jsonResponse([
        'success' => true,
        'added' => count($added),
        'message' => count($added) . ' user(s) assigned to role'
    ]);
}

/**
 * Add Google Group mapping to a role
 */
function addGoogleGroup($orgRole, $data)
{
    if (empty($data['role_id']) || empty($data['google_group_email'])) {
        jsonResponse(['error' => 'role_id and google_group_email required'], 400);
    }

    $db = Database::getInstance()->getConnection();
    $currentUser = Auth::getCurrentUser();

    $stmt = $db->prepare("
        INSERT INTO org_role_google_groups (org_role_id, google_group_email, created_by)
        VALUES (:role_id, :email, :created_by)
        ON DUPLICATE KEY UPDATE is_active = 1, updated_at = NOW()
    ");

    $stmt->execute([
        'role_id' => $data['role_id'],
        'email' => strtolower(trim($data['google_group_email'])),
        'created_by' => $currentUser['id']
    ]);

    // Log the mapping
    $role = $orgRole->getById($data['role_id']);
    AuditLogger::log('org_role_google_group_added', 'org_roles', $data['role_id'], null, [
        'role_name' => $role['name'],
        'google_group' => $data['google_group_email']
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Google Group mapped successfully'
    ]);
}

/**
 * Sync all Google Group memberships
 */
function syncGoogleGroups($orgRole)
{
    // This requires Google Workspace API access via service account
    // For now, return a placeholder - will implement with Google API

    jsonResponse([
        'success' => true,
        'message' => 'Google Groups sync initiated',
        'note' => 'Full implementation requires Google Workspace Admin API'
    ]);
}

/**
 * Get Microsoft Group mappings for a role
 */
function getMicrosoftGroupMappings($orgRole)
{
    if (empty($_GET['role_id'])) {
        jsonResponse(['error' => 'role_id required'], 400);
    }

    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT id, azure_group_id, azure_group_name, is_active, sync_on_login,
               last_sync_at, created_at
        FROM org_role_microsoft_groups
        WHERE org_role_id = :role_id AND is_active = 1
        ORDER BY azure_group_name ASC
    ");
    $stmt->execute(['role_id' => $_GET['role_id']]);

    jsonResponse([
        'success' => true,
        'groups' => $stmt->fetchAll(\PDO::FETCH_ASSOC)
    ]);
}

/**
 * Add Microsoft Azure AD Group mapping to a role
 */
function addMicrosoftGroup($orgRole, $data)
{
    if (empty($data['role_id']) || empty($data['azure_group_id'])) {
        jsonResponse(['error' => 'role_id and azure_group_id required'], 400);
    }

    $db = Database::getInstance()->getConnection();
    $currentUser = Auth::getCurrentUser();

    // Validate Azure Group ID format (should be a GUID)
    $groupId = trim($data['azure_group_id']);
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $groupId)) {
        jsonResponse(['error' => 'Invalid Azure Group ID format. Must be a GUID (e.g., 12345678-1234-1234-1234-123456789abc)'], 400);
    }

    $stmt = $db->prepare("
        INSERT INTO org_role_microsoft_groups
        (org_role_id, azure_group_id, azure_group_name, created_by)
        VALUES (:role_id, :group_id, :group_name, :created_by)
        ON DUPLICATE KEY UPDATE is_active = 1, azure_group_name = :group_name, updated_at = NOW()
    ");

    $stmt->execute([
        'role_id' => $data['role_id'],
        'group_id' => $groupId,
        'group_name' => $data['azure_group_name'] ?? null,
        'created_by' => $currentUser['id']
    ]);

    // Log the mapping
    $role = $orgRole->getById($data['role_id']);
    AuditLogger::log('org_role_microsoft_group_added', 'org_roles', $data['role_id'], null, [
        'role_name' => $role['name'],
        'azure_group_id' => $groupId,
        'azure_group_name' => $data['azure_group_name'] ?? null
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Microsoft Group mapped successfully'
    ]);
}

/**
 * Sync all Microsoft Azure AD Group memberships
 */
function syncMicrosoftGroups($orgRole)
{
    // This requires Microsoft Graph API access
    // For now, return a placeholder - will implement with Graph SDK

    jsonResponse([
        'success' => true,
        'message' => 'Microsoft Groups sync initiated',
        'note' => 'Full implementation requires Microsoft Graph API'
    ]);
}

/**
 * PUT handler - Update role
 */
function handlePut($orgRole)
{
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['id'])) {
        jsonResponse(['error' => 'Role ID required'], 400);
    }

    $roleId = (int)$data['id'];
    $oldRole = $orgRole->getById($roleId);

    if (!$oldRole) {
        jsonResponse(['error' => 'Role not found'], 404);
    }

    $orgRole->update($roleId, $data['name'], $data['description'] ?? null);

    // Log the update
    AuditLogger::log('org_role_updated', 'org_roles', $roleId, $oldRole, [
        'name' => $data['name'],
        'description' => $data['description'] ?? null
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Organization role updated successfully'
    ]);
}

/**
 * DELETE handler - Soft delete role or remove group mapping
 */
function handleDelete($orgRole)
{
    parse_str(file_get_contents('php://input'), $data);
    $action = $_GET['action'] ?? null;

    // Handle Microsoft Group removal
    if ($action === 'remove-microsoft-group') {
        removeMicrosoftGroup($data);
        return;
    }

    // Handle role deletion
    if (empty($data['id'])) {
        jsonResponse(['error' => 'Role ID required'], 400);
    }

    $roleId = (int)$data['id'];
    $role = $orgRole->getById($roleId);

    if (!$role) {
        jsonResponse(['error' => 'Role not found'], 404);
    }

    // Check if role has users
    $userCount = $orgRole->getRoleUserCount($roleId);
    if ($userCount > 0) {
        jsonResponse(['error' => "Cannot delete role with {$userCount} assigned user(s). Remove users first."], 400);
    }

    $orgRole->delete($roleId);

    // Log the deletion
    AuditLogger::log('org_role_deleted', 'org_roles', $roleId, $role, null);

    jsonResponse([
        'success' => true,
        'message' => 'Organization role deleted successfully'
    ]);
}

/**
 * Remove Microsoft Group mapping
 */
function removeMicrosoftGroup($data)
{
    if (empty($data['group_id'])) {
        jsonResponse(['error' => 'group_id required'], 400);
    }

    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        UPDATE org_role_microsoft_groups
        SET is_active = 0, updated_at = NOW()
        WHERE id = :group_id
    ");

    $stmt->execute(['group_id' => $data['group_id']]);

    jsonResponse([
        'success' => true,
        'message' => 'Microsoft Group mapping removed successfully'
    ]);
}
