<?php

namespace Hub;

use PDO;

/**
 * Google Groups Sync Service
 * 
 * Provides on-demand Google Groups → Organization Role synchronization.
 * Uses a service account with domain-wide delegation (no user access token needed).
 * Can sync a single user or all active users in bulk.
 * 
 * Flow:
 *   1. Fetch user's Google Groups via Admin Directory API (service account)
 *   2. Match groups against org_role_google_groups table mappings
 *   3. Assign matched org roles via OrgRole::assignToUser()
 *   4. Also check GOOGLE_GROUP_ROLE_ASSOCIATIONS env var for base role upgrades
 *   5. Return detailed results for admin visibility
 */
class GoogleGroupSync
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Check if Google Groups sync is properly configured
     * Returns array with 'configured' bool and 'issues' array
     */
    public function checkConfiguration(): array
    {
        $issues = [];

        $enabled = filter_var($_ENV['ENABLE_GOOGLE_GROUPS'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            $issues[] = 'ENABLE_GOOGLE_GROUPS is not set to true';
        }

        $serviceAccountPath = $_ENV['GOOGLE_SERVICE_ACCOUNT_JSON'] ?? null;
        if (!$serviceAccountPath) {
            $issues[] = 'GOOGLE_SERVICE_ACCOUNT_JSON not configured';
        } elseif (!file_exists($serviceAccountPath)) {
            $issues[] = "Service account JSON file not found: {$serviceAccountPath}";
        }

        $adminEmail = $_ENV['GOOGLE_ADMIN_EMAIL'] ?? null;
        if (!$adminEmail) {
            $issues[] = 'GOOGLE_ADMIN_EMAIL not configured';
        }

        // Check if there are any group→role mappings configured
        $mappingCount = (int) $this->db->query(
            "SELECT COUNT(*) FROM org_role_google_groups WHERE is_active = 1"
        )->fetchColumn();

        $envAssociations = $_ENV['GOOGLE_GROUP_ROLE_ASSOCIATIONS'] ?? '';

        if ($mappingCount === 0 && empty($envAssociations)) {
            $issues[] = 'No Google Group→Role mappings configured (neither org_role_google_groups nor GOOGLE_GROUP_ROLE_ASSOCIATIONS)';
        }

        return [
            'configured' => $enabled && $serviceAccountPath && $adminEmail && empty(array_filter($issues, fn($i) => !str_contains($i, 'mapping'))),
            'enabled' => $enabled,
            'service_account' => $serviceAccountPath ? basename($serviceAccountPath) : null,
            'admin_email' => $adminEmail,
            'org_role_mappings' => $mappingCount,
            'env_associations' => !empty($envAssociations),
            'issues' => $issues,
        ];
    }

    /**
     * Get a user's Google Groups using the service account
     * Returns array of group email addresses
     */
    public function getUserGroups(string $userEmail): array
    {
        $serviceAccountPath = $_ENV['GOOGLE_SERVICE_ACCOUNT_JSON'] ?? null;
        $adminEmail = $_ENV['GOOGLE_ADMIN_EMAIL'] ?? null;

        if (!$serviceAccountPath || !$adminEmail) {
            throw new \RuntimeException('Google Groups not configured: missing GOOGLE_SERVICE_ACCOUNT_JSON or GOOGLE_ADMIN_EMAIL');
        }

        if (!file_exists($serviceAccountPath)) {
            throw new \RuntimeException("Service account JSON not found: {$serviceAccountPath}");
        }

        // Initialize Google Client with service account
        $client = new \Google_Client();
        $client->setAuthConfig($serviceAccountPath);
        $client->setSubject($adminEmail);
        $client->addScope(\Google_Service_Directory::ADMIN_DIRECTORY_GROUP_READONLY);

        $service = new \Google_Service_Directory($client);

        $groups = $service->groups->listGroups(['userKey' => $userEmail]);
        $groupEmails = [];

        foreach ($groups->getGroups() as $group) {
            $groupEmails[] = strtolower($group->getEmail());
        }

        return $groupEmails;
    }

    /**
     * Sync a single user's Google Groups → org roles
     * Returns detailed result array
     */
    public function syncUser(int $userId, int $syncedBy = 0): array
    {
        $user = $this->db->prepare("SELECT id, email, name, role FROM users WHERE id = :id AND is_active = 1");
        $user->execute(['id' => $userId]);
        $user = $user->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return [
                'success' => false,
                'user_id' => $userId,
                'error' => 'User not found or inactive',
            ];
        }

        return $this->syncUserByEmail($user, $syncedBy);
    }

    /**
     * Sync all active users' Google Groups
     * Returns array of results per user
     */
    public function syncAllUsers(int $syncedBy = 0): array
    {
        $stmt = $this->db->query("SELECT id, email, name, role FROM users WHERE is_active = 1 ORDER BY name");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [
            'total' => count($users),
            'synced' => 0,
            'errors' => 0,
            'skipped' => 0,
            'roles_assigned' => 0,
            'users' => [],
        ];

        foreach ($users as $user) {
            $result = $this->syncUserByEmail($user, $syncedBy);
            $results['users'][] = $result;

            if ($result['success']) {
                $results['synced']++;
                $results['roles_assigned'] += count($result['matched_roles'] ?? []);
            } elseif (($result['error'] ?? '') === 'No Google Groups found') {
                $results['skipped']++;
            } else {
                $results['errors']++;
            }
        }

        // Log the bulk sync
        if (class_exists('Hub\\AuditLogger')) {
            AuditLogger::logUpdate(
                'system',
                0,
                ['action' => 'google_groups_bulk_sync_started', 'total_users' => $results['total']],
                [
                    'action' => 'google_groups_bulk_sync_completed',
                    'synced' => $results['synced'],
                    'errors' => $results['errors'],
                    'skipped' => $results['skipped'],
                    'roles_assigned' => $results['roles_assigned'],
                    'synced_by' => $syncedBy,
                ]
            );
        }

        return $results;
    }

    /**
     * Preview a user's Google Groups without syncing
     */
    public function previewUser(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT id, email, name, role FROM users WHERE id = :id AND is_active = 1");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['success' => false, 'error' => 'User not found or inactive'];
        }

        try {
            $groups = $this->getUserGroups($user['email']);

            // Find what roles WOULD be matched
            $wouldMatch = $this->findMatchingOrgRoles($groups);
            $envRoles = $this->findEnvRoleAssociations($groups);

            // Get current org roles
            $currentStmt = $this->db->prepare(
                "SELECT orr.id, orr.name 
                 FROM org_roles orr 
                 JOIN user_org_roles uor ON orr.id = uor.org_role_id 
                 WHERE uor.user_id = :user_id AND orr.is_active = 1"
            );
            $currentStmt->execute(['user_id' => $userId]);
            $currentRoles = $currentStmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'user' => ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email']],
                'groups' => $groups,
                'groups_count' => count($groups),
                'current_org_roles' => $currentRoles,
                'would_match_org_roles' => $wouldMatch,
                'would_match_env_roles' => $envRoles,
                'preview_only' => true,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'user' => ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email']],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Internal: Sync a single user's groups → org roles
     */
    private function syncUserByEmail(array $user, int $syncedBy): array
    {
        $result = [
            'success' => false,
            'user_id' => $user['id'],
            'user_name' => $user['name'],
            'user_email' => $user['email'],
            'groups' => [],
            'matched_roles' => [],
            'base_role_update' => null,
        ];

        try {
            // Get user's Google Groups
            $groups = $this->getUserGroups($user['email']);
            $result['groups'] = $groups;
            $result['groups_count'] = count($groups);

            if (empty($groups)) {
                $result['error'] = 'No Google Groups found';
                $result['success'] = true; // Not an error, just no groups
                return $result;
            }

            // Match against org_role_google_groups table
            $matchedOrgRoles = $this->findMatchingOrgRoles($groups);
            $result['matched_roles'] = $matchedOrgRoles;

            // Assign org roles if any matched
            if (!empty($matchedOrgRoles)) {
                $orgRoleIds = array_column($matchedOrgRoles, 'org_role_id');
                $orgRole = new OrgRole();
                $orgRole->assignToUser($user['id'], $orgRoleIds, $syncedBy);
            }

            // Check GOOGLE_GROUP_ROLE_ASSOCIATIONS for base role updates
            $envRoles = $this->findEnvRoleAssociations($groups);
            if (!empty($envRoles)) {
                $result['env_role_matches'] = $envRoles;
                // Apply the highest-priority base role from env associations
                $roleHierarchy = ['super_admin' => 5, 'admin' => 4, 'principal' => 3, 'staff' => 2, 'student' => 1];
                $highestRole = null;
                $highestPriority = 0;

                foreach ($envRoles as $envRole) {
                    $priority = $roleHierarchy[$envRole['role']] ?? 0;
                    if ($priority > $highestPriority) {
                        $highestPriority = $priority;
                        $highestRole = $envRole['role'];
                    }
                }

                // Only upgrade, never downgrade
                $currentPriority = $roleHierarchy[$user['role']] ?? 0;
                if ($highestPriority > $currentPriority) {
                    $this->db->prepare("UPDATE users SET role = :role WHERE id = :id")
                        ->execute(['role' => $highestRole, 'id' => $user['id']]);
                    $result['base_role_update'] = [
                        'from' => $user['role'],
                        'to' => $highestRole,
                    ];
                }
            }

            $result['success'] = true;

            // Audit log
            if (class_exists('Hub\\AuditLogger')) {
                AuditLogger::logUpdate(
                    'users',
                    $user['id'],
                    ['action' => 'google_groups_sync', 'groups_count' => count($groups)],
                    [
                        'action' => 'google_groups_synced',
                        'groups' => $groups,
                        'matched_org_roles' => array_column($matchedOrgRoles, 'role_name'),
                        'base_role_update' => $result['base_role_update'],
                        'synced_by' => $syncedBy,
                    ]
                );
            }

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            error_log("Google Groups sync failed for {$user['email']}: " . $e->getMessage());
        }

        return $result;
    }

    /**
     * Match user's groups against org_role_google_groups table
     */
    private function findMatchingOrgRoles(array $userGroups): array
    {
        $mappings = $this->db->query(
            "SELECT orgg.org_role_id, orgg.google_group_email, orr.name as role_name
             FROM org_role_google_groups orgg
             JOIN org_roles orr ON orgg.org_role_id = orr.id
             WHERE orgg.is_active = 1 AND orr.is_active = 1"
        )->fetchAll(PDO::FETCH_ASSOC);

        $matched = [];
        foreach ($mappings as $mapping) {
            foreach ($userGroups as $userGroup) {
                if ($this->matchesGroupPattern($userGroup, $mapping['google_group_email'])) {
                    $matched[] = [
                        'org_role_id' => (int) $mapping['org_role_id'],
                        'role_name' => $mapping['role_name'],
                        'matched_group' => $userGroup,
                        'pattern' => $mapping['google_group_email'],
                    ];
                    break;
                }
            }
        }

        return $matched;
    }

    /**
     * Match user's groups against GOOGLE_GROUP_ROLE_ASSOCIATIONS env var
     */
    private function findEnvRoleAssociations(array $userGroups): array
    {
        $associations = $_ENV['GOOGLE_GROUP_ROLE_ASSOCIATIONS'] ?? '';
        if (empty($associations)) {
            return [];
        }

        $matched = [];
        $mappings = explode('|', $associations);

        foreach ($mappings as $mapping) {
            $mapping = trim($mapping);
            if (empty($mapping) || !strpos($mapping, ':')) {
                continue;
            }

            [$groupPattern, $rolesStr] = explode(':', $mapping, 2);
            $groupPattern = trim($groupPattern);
            $roles = array_map('trim', explode(',', $rolesStr));

            foreach ($userGroups as $userGroup) {
                if ($this->matchesGroupPattern($userGroup, $groupPattern)) {
                    foreach ($roles as $role) {
                        $matched[] = [
                            'role' => $role,
                            'matched_group' => $userGroup,
                            'pattern' => $groupPattern,
                        ];
                    }
                    break;
                }
            }
        }

        return $matched;
    }

    /**
     * Match a group email against a pattern (supports wildcards)
     * Extracted from Auth.php for reuse
     */
    private function matchesGroupPattern(string $userGroup, string $pattern): bool
    {
        if (strpos($pattern, '*') === false) {
            return strtolower($userGroup) === strtolower($pattern);
        }

        $regexPattern = preg_quote($pattern, '/');
        $regexPattern = str_replace('\*', '.*', $regexPattern);

        return preg_match('/^' . $regexPattern . '$/i', $userGroup) === 1;
    }
}
