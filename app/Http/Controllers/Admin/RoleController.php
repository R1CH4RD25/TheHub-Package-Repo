<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Hub\Database;

class RoleController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get all roles with their permissions
     */
    public function index(): JsonResponse
    {
        $roles = $this->db->fetchAll("
            SELECT r.*,
                   COUNT(DISTINCT rp.permission_id) as permission_count,
                   COUNT(DISTINCT ur.user_id) as user_count
            FROM roles r
            LEFT JOIN role_permissions rp ON r.id = rp.role_id
            LEFT JOIN user_roles ur ON r.id = ur.role_id
            GROUP BY r.id
            ORDER BY
                CASE r.name
                    WHEN 'super_admin' THEN 1
                    WHEN 'admin' THEN 2
                    WHEN 'maintenance_director' THEN 3
                    WHEN 'maintenance' THEN 4
                    ELSE 5
                END,
                r.display_name
        ");

        return response()->json($roles);
    }

    /**
     * Get a single role with full details
     */
    public function show(int $id): JsonResponse
    {
        $role = $this->db->fetchOne("SELECT * FROM roles WHERE id = ?", [$id]);

        if (!$role) {
            return response()->json(['error' => 'Role not found'], 404);
        }

        // Get permissions for this role
        $permissions = $this->db->fetchAll("
            SELECT p.*, rp.granted_at, u.name as granted_by_name
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            LEFT JOIN users u ON rp.granted_by = u.id
            WHERE rp.role_id = ?
            ORDER BY p.category, p.display_name
        ", [$id]);

        // Get users with this role
        $users = $this->db->fetchAll("
            SELECT u.id, u.name, u.email, ur.granted_at
            FROM users u
            JOIN user_roles ur ON u.id = ur.user_id
            WHERE ur.role_id = ?
            ORDER BY u.name
        ", [$id]);

        $role['permissions'] = $permissions;
        $role['users'] = $users;

        return response()->json($role);
    }

    /**
     * Get all permissions grouped by category
     */
    public function permissions(): JsonResponse
    {
        $permissions = $this->db->fetchAll("
            SELECT * FROM permissions
            ORDER BY category, display_name
        ");

        // Group by category
        $grouped = [];
        foreach ($permissions as $perm) {
            $category = $perm['category'];
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $perm;
        }

        return response()->json($grouped);
    }

    /**
     * Create a new role
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->all();
        $currentUser = $request->attributes->get('user');

        // Validate required fields
        if (empty($data['name']) || empty($data['display_name'])) {
            return response()->json(['error' => 'Name and display name are required'], 400);
        }

        // Check if role name already exists
        $existing = $this->db->fetchOne("SELECT id FROM roles WHERE name = ?", [$data['name']]);
        if ($existing) {
            return response()->json(['error' => 'Role name already exists'], 400);
        }

        // Create role
        $this->db->execute(
            "INSERT INTO roles (name, display_name, description, is_system) VALUES (?, ?, ?, ?)",
            [
                $data['name'],
                $data['display_name'],
                $data['description'] ?? null,
                false // Custom roles are never system roles
            ]
        );

        $roleId = $this->db->lastInsertId();

        // Assign permissions if provided
        if (!empty($data['permissions']) && is_array($data['permissions'])) {
            foreach ($data['permissions'] as $permissionId) {
                $this->db->execute(
                    "INSERT INTO role_permissions (role_id, permission_id, granted_by) VALUES (?, ?, ?)",
                    [$roleId, $permissionId, $currentUser['id']]
                );
            }
        }

        return response()->json([
            'success' => true,
            'role_id' => $roleId,
            'message' => 'Role created successfully'
        ]);
    }

    /**
     * Update an existing role
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->all();
        $currentUser = $request->attributes->get('user');

        $role = $this->db->fetchOne("SELECT * FROM roles WHERE id = ?", [$id]);

        if (!$role) {
            return response()->json(['error' => 'Role not found'], 404);
        }

        // Prevent editing system roles' core properties
        if ($role['is_system'] && isset($data['name'])) {
            return response()->json(['error' => 'Cannot rename system roles'], 403);
        }

        // Update basic info
        if (isset($data['display_name']) || isset($data['description'])) {
            $updates = [];
            $params = [];

            if (isset($data['display_name'])) {
                $updates[] = "display_name = ?";
                $params[] = $data['display_name'];
            }
            if (isset($data['description'])) {
                $updates[] = "description = ?";
                $params[] = $data['description'];
            }

            $params[] = $id;
            $this->db->execute(
                "UPDATE roles SET " . implode(", ", $updates) . " WHERE id = ?",
                $params
            );
        }

        // Update permissions if provided
        if (isset($data['permissions']) && is_array($data['permissions'])) {
            // Remove all existing permissions
            $this->db->execute("DELETE FROM role_permissions WHERE role_id = ?", [$id]);

            // Add new permissions
            foreach ($data['permissions'] as $permissionId) {
                $this->db->execute(
                    "INSERT INTO role_permissions (role_id, permission_id, granted_by) VALUES (?, ?, ?)",
                    [$id, $permissionId, $currentUser['id']]
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully'
        ]);
    }

    /**
     * Delete a role
     */
    public function destroy(int $id): JsonResponse
    {
        $role = $this->db->fetchOne("SELECT * FROM roles WHERE id = ?", [$id]);

        if (!$role) {
            return response()->json(['error' => 'Role not found'], 404);
        }

        if ($role['is_system']) {
            return response()->json(['error' => 'Cannot delete system roles'], 403);
        }

        // Check if any users have this role
        $userCount = $this->db->fetchOne("SELECT COUNT(*) as cnt FROM user_roles WHERE role_id = ?", [$id]);
        if ($userCount['cnt'] > 0) {
            return response()->json([
                'error' => 'Cannot delete role: ' . $userCount['cnt'] . ' user(s) currently have this role'
            ], 400);
        }

        $this->db->execute("DELETE FROM roles WHERE id = ?", [$id]);

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully'
        ]);
    }

    /**
     * Assign role to user
     */
    public function assignToUser(Request $request): JsonResponse
    {
        $data = $request->all();
        $currentUser = $request->attributes->get('user');

        if (empty($data['user_id']) || empty($data['role_id'])) {
            return response()->json(['error' => 'User ID and Role ID are required'], 400);
        }

        // Check if assignment already exists
        $existing = $this->db->fetchOne(
            "SELECT id FROM user_roles WHERE user_id = ? AND role_id = ?",
            [$data['user_id'], $data['role_id']]
        );

        if ($existing) {
            return response()->json(['error' => 'User already has this role'], 400);
        }

        $this->db->execute(
            "INSERT INTO user_roles (user_id, role_id, granted_by) VALUES (?, ?, ?)",
            [$data['user_id'], $data['role_id'], $currentUser['id']]
        );

        return response()->json([
            'success' => true,
            'message' => 'Role assigned to user successfully'
        ]);
    }

    /**
     * Remove role from user
     */
    public function removeFromUser(Request $request): JsonResponse
    {
        $data = $request->all();

        if (empty($data['user_id']) || empty($data['role_id'])) {
            return response()->json(['error' => 'User ID and Role ID are required'], 400);
        }

        $this->db->execute(
            "DELETE FROM user_roles WHERE user_id = ? AND role_id = ?",
            [$data['user_id'], $data['role_id']]
        );

        return response()->json([
            'success' => true,
            'message' => 'Role removed from user successfully'
        ]);
    }

    /**
     * Get user's effective permissions (from all their roles)
     */
    public function userPermissions(int $userId): JsonResponse
    {
        $permissions = $this->db->fetchAll("
            SELECT DISTINCT p.*
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            JOIN user_roles ur ON rp.role_id = ur.role_id
            WHERE ur.user_id = ?
            ORDER BY p.category, p.display_name
        ", [$userId]);

        return response()->json($permissions);
    }
}
