<?php
/**
 * Section Type Configuration
 * 
 * Centralized definition of section types and their behaviors.
 * This allows adding new section types without modifying multiple files.
 */

namespace Hub;

class SectionType {
    
    /**
     * Get all section type definitions
     * 
     * @return array Associative array of section types with their configurations
     */
    public static function getAll(): array {
        return [
            'recording' => [
                'value' => 'recording',
                'label' => 'Recording/Data Entry',
                'description' => 'For recording and tracking data (attendance, logs, surveys)',
                'icon' => '📊',
                'features' => [
                    'has_export' => true,
                    'requires_approval' => false,
                    'send_notifications' => false,
                    'has_workflow' => false
                ],
                'color' => '#2196F3' // Blue
            ],
            'request_form' => [
                'value' => 'request_form',
                'label' => 'Request Form',
                'description' => 'Forms that require submission and approval workflows',
                'icon' => '📝',
                'features' => [
                    'has_export' => true,
                    'requires_approval' => true,
                    'send_notifications' => true,
                    'has_workflow' => true
                ],
                'color' => '#FF9800' // Orange
            ],
            'scheduled_report' => [
                'value' => 'scheduled_report',
                'label' => 'Scheduled Report',
                'description' => 'Automated reports sent via email on schedule',
                'icon' => '📧',
                'features' => [
                    'has_export' => true,
                    'requires_approval' => false,
                    'send_notifications' => true,
                    'has_workflow' => false
                ],
                'color' => '#4CAF50' // Green
            ],
            'hub' => [
                'value' => 'hub',
                'label' => 'Hub/Dashboard',
                'description' => 'Central dashboard or portal for specific user groups',
                'icon' => '🏠',
                'features' => [
                    'has_export' => false,
                    'requires_approval' => false,
                    'send_notifications' => false,
                    'has_workflow' => false
                ],
                'color' => '#9C27B0' // Purple
            ],
            'other' => [
                'value' => 'other',
                'label' => 'Other',
                'description' => 'Custom section with specific functionality',
                'icon' => '🔧',
                'features' => [
                    'has_export' => false,
                    'requires_approval' => false,
                    'send_notifications' => false,
                    'has_workflow' => false
                ],
                'color' => '#607D8B' // Blue Grey
            ]
        ];
    }

    /**
     * Get section types sorted by common usage
     * 
     * @return array Ordered array of section types
     */
    public static function getOrdered(): array {
        $types = self::getAll();
        return [
            $types['recording'],
            $types['request_form'],
            $types['scheduled_report'],
            $types['hub'],
            $types['other']
        ];
    }

    /**
     * Get array of valid section type values
     * 
     * @return array
     */
    public static function getValues(): array {
        return array_keys(self::getAll());
    }

    /**
     * Get display label for a section type
     * 
     * @param string $type
     * @return string
     */
    public static function getLabel(string $type): string {
        $types = self::getAll();
        return $types[$type]['label'] ?? ucfirst(str_replace('_', ' ', $type));
    }

    /**
     * Get icon for a section type
     * 
     * @param string $type
     * @return string
     */
    public static function getIcon(string $type): string {
        $types = self::getAll();
        return $types[$type]['icon'] ?? '📁';
    }

    /**
     * Get color for a section type
     * 
     * @param string $type
     * @return string
     */
    public static function getColor(string $type): string {
        $types = self::getAll();
        return $types[$type]['color'] ?? '#666666';
    }

    /**
     * Check if section type supports a specific feature
     * 
     * @param string $type
     * @param string $feature (has_export, requires_approval, send_notifications, has_workflow)
     * @return bool
     */
    public static function hasFeature(string $type, string $feature): bool {
        $types = self::getAll();
        return $types[$type]['features'][$feature] ?? false;
    }

    /**
     * Check if section type value is valid
     * 
     * @param string $type
     * @return bool
     */
    public static function isValid(string $type): bool {
        return array_key_exists($type, self::getAll());
    }

    /**
     * Get SQL ENUM string for database
     * 
     * @return string
     */
    public static function getSqlEnum(): string {
        $values = array_map(function($value) {
            return "'{$value}'";
        }, self::getValues());
        
        return "ENUM(" . implode(', ', $values) . ")";
    }

    /**
     * Export section types for JavaScript
     * 
     * @return string JSON
     */
    public static function getForJavaScript(): string {
        return json_encode(array_values(self::getAll()), JSON_PRETTY_PRINT);
    }
}
