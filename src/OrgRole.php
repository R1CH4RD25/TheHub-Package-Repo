<?php

namespace Hub;

use PDO;

/**
 * Organization Role Management
 * 
 * Handles custom roles defined by each organization.
 * These roles are mapped to package roles to grant permissions.
 */
class OrgRole
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Create a new organization role
     */
    public function create(string $name, ?string $description = null): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO org_roles (name, description) VALUES (:name, :description)"
        );
        
        $stmt->execute([
            'name' => $name,
            'description' => $description
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Get all active organization roles
     */
    public function getAll(): array
    {
        $stmt = $this->db->query(
            "SELECT * FROM org_roles WHERE is_active = 1 ORDER BY name"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a specific organization role by ID
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM org_roles WHERE id = :id AND is_active = 1"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Update an organization role
     */
    public function update(int $id, string $name, ?string $description = null): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE org_roles SET name = :name, description = :description WHERE id = :id"
        );
        
        return $stmt->execute([
            'id' => $id,
            'name' => $name,
            'description' => $description
        ]);
    }

    /**
     * Soft delete an organization role
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE org_roles SET is_active = 0 WHERE id = :id"
        );
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Assign organization role(s) to a user
     */
    public function assignToUser(int $userId, array $orgRoleIds, int $assignedBy): bool
    {
        // Remove existing assignments
        $this->removeFromUser($userId);

        if (empty($orgRoleIds)) {
            return true;
        }

        // Add new assignments
        $stmt = $this->db->prepare(
            "INSERT INTO user_org_roles (user_id, org_role_id, assigned_by) VALUES (:user_id, :org_role_id, :assigned_by)"
        );

        foreach ($orgRoleIds as $orgRoleId) {
            $stmt->execute([
                'user_id' => $userId,
                'org_role_id' => $orgRoleId,
                'assigned_by' => $assignedBy
            ]);
        }

        return true;
    }

    /**
     * Remove all organization roles from a user
     */
    public function removeFromUser(int $userId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM user_org_roles WHERE user_id = :user_id");
        return $stmt->execute(['user_id' => $userId]);
    }

    /**
     * Get all organization roles assigned to a user
     */
    public function getUserRoles(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT or.* FROM org_roles or
             JOIN user_org_roles uor ON or.id = uor.org_role_id
             WHERE uor.user_id = :user_id AND or.is_active = 1
             ORDER BY or.name"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get user IDs that have a specific organization role
     */
    public function getUsersByRole(int $orgRoleId): array
    {
        $stmt = $this->db->prepare(
            "SELECT user_id FROM user_org_roles WHERE org_role_id = :org_role_id"
        );
        $stmt->execute(['org_role_id' => $orgRoleId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Check if a user has a specific organization role
     */
    public function userHasRole(int $userId, int $orgRoleId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM user_org_roles WHERE user_id = :user_id AND org_role_id = :org_role_id"
        );
        $stmt->execute([
            'user_id' => $userId,
            'org_role_id' => $orgRoleId
        ]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Get count of users with a specific organization role
     */
    public function getRoleUserCount(int $orgRoleId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM user_org_roles WHERE org_role_id = :org_role_id"
        );
        $stmt->execute(['org_role_id' => $orgRoleId]);
        return (int) $stmt->fetchColumn();
    }
}
