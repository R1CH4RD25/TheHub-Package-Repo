<?php

namespace Hub;

/**
 * Centralized Role Configuration
 * Define all platform roles in ONE place
 */
class Roles
{
    /**
     * Get all available roles with their metadata
     * This is the SINGLE SOURCE OF TRUTH for all roles
     * 
     * @return array Array of roles with hierarchy, labels, and descriptions
     */
    public static function getAll(): array
    {
        return [
            'super_admin' => [
                'value' => 'super_admin',
                'label' => 'Super Admin',
                'description' => 'Full platform control (manage sections, system settings)',
                'hierarchy' => 100,
                'color' => '#d32f2f'
            ],
            'admin' => [
                'value' => 'admin',
                'label' => 'Admin',
                'description' => 'Can manage users and section access',
                'hierarchy' => 90
            ],
            'principal' => [
                'value' => 'principal',
                'label' => 'Principal',
                'description' => 'Principal-level access',
                'hierarchy' => 80
            ],
            'counselor' => [
                'value' => 'counselor',
                'label' => 'Counselor',
                'description' => 'Counselor access to student services',
                'hierarchy' => 70
            ],
            'substitute_manager' => [
                'value' => 'substitute_manager',
                'label' => 'Substitute Manager',
                'description' => 'Can manage substitute requests',
                'hierarchy' => 60
            ],
            'business_manager' => [
                'value' => 'business_manager',
                'label' => 'Business Manager',
                'description' => 'Manages financial operations and reimbursements',
                'hierarchy' => 55
            ],
            'maintenance_director' => [
                'value' => 'maintenance_director',
                'label' => 'Maintenance Director',
                'description' => 'Facility maintenance operations management',
                'hierarchy' => 50
            ],
            'custodial_manager' => [
                'value' => 'custodial_manager',
                'label' => 'Custodial Manager',
                'description' => 'Manages custodial staff and operations',
                'hierarchy' => 45
            ],
            'maintenance_staff' => [
                'value' => 'maintenance_staff',
                'label' => 'Maintenance Staff',
                'description' => 'Facility maintenance and operations',
                'hierarchy' => 40
            ],
            'custodial' => [
                'value' => 'custodial',
                'label' => 'Custodial',
                'description' => 'Custodial and facilities access',
                'hierarchy' => 35
            ],
            'cafeteria' => [
                'value' => 'cafeteria',
                'label' => 'Cafeteria',
                'description' => 'Cafeteria operations access',
                'hierarchy' => 30
            ],
            'student' => [
                'value' => 'student',
                'label' => 'Student',
                'description' => 'Student access to allowed services',
                'hierarchy' => 20
            ],
            'staff' => [
                'value' => 'staff',
                'label' => 'Staff',
                'description' => 'Basic access to assigned sections',
                'hierarchy' => 10
            ]
        ];
    }

    /**
     * Get array of role values only
     */
    public static function getValues(): array
    {
        return array_keys(self::getAll());
    }

    /**
     * Get roles ordered by hierarchy (highest to lowest)
     */
    public static function getOrdered(): array
    {
        $roles = self::getAll();
        uasort($roles, function($a, $b) {
            return $b['hierarchy'] <=> $a['hierarchy'];
        });
        return $roles;
    }

    /**
     * Get role label from value
     */
    public static function getLabel(string $value): string
    {
        $roles = self::getAll();
        return $roles[$value]['label'] ?? ucfirst(str_replace('_', ' ', $value));
    }

    /**
     * Get role hierarchy value
     */
    public static function getHierarchy(string $value): int
    {
        $roles = self::getAll();
        return $roles[$value]['hierarchy'] ?? 0;
    }

    /**
     * Get highest role from array of roles
     */
    public static function getHighest(array $roleValues): string
    {
        $highestValue = 'staff';
        $highestHierarchy = 0;

        foreach ($roleValues as $role) {
            $hierarchy = self::getHierarchy($role);
            if ($hierarchy > $highestHierarchy) {
                $highestHierarchy = $hierarchy;
                $highestValue = $role;
            }
        }

        return $highestValue;
    }

    /**
     * Validate if role exists
     */
    public static function isValid(string $value): bool
    {
        return isset(self::getAll()[$value]);
    }

    /**
     * Get SQL ENUM string for database columns
     */
    public static function getSqlEnum(): string
    {
        $values = array_map(function($v) {
            return "'$v'";
        }, self::getValues());
        return implode(', ', $values);
    }

    /**
     * Get roles formatted for JavaScript
     */
    public static function getForJavaScript(): string
    {
        return json_encode(array_values(self::getOrdered()), JSON_PRETTY_PRINT);
    }
    
    /**
     * Get only active roles from database settings
     * 
     * @return array Active roles only
     */
    public static function getActive(): array
    {
        try {
            $db = Database::getInstance();
            $stmt = $db->query("
                SELECT role_value 
                FROM role_settings 
                WHERE is_active = TRUE 
                ORDER BY display_order
            ");
            $activeRoles = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            // Filter all roles to only include active ones
            $allRoles = self::getAll();
            return array_filter($allRoles, function($role) use ($activeRoles) {
                return in_array($role['value'], $activeRoles);
            });
        } catch (\Exception $e) {
            // If table doesn't exist or query fails, return all roles
            return self::getAll();
        }
    }
    
    /**
     * Check if a specific role is active
     * 
     * @param string $roleValue
     * @return bool
     */
    public static function isActive(string $roleValue): bool
    {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                SELECT is_active 
                FROM role_settings 
                WHERE role_value = :role
            ");
            $stmt->execute(['role' => $roleValue]);
            $result = $stmt->fetch();
            
            return $result ? (bool)$result['is_active'] : true; // Default to true if not found
        } catch (\Exception $e) {
            return true; // Default to active if query fails
        }
    }
}
