<?php
/**
 * Bullying Report Package Configuration
 * 
 * This file contains package-specific settings that can be customized
 * per installation. These settings are independent of global site settings.
 */

return [
    'display_name' => 'Bullying & Harassment Report',
    'version' => '1.0.0',
    'author' => 'TheHub',
    
    // Emergency contact information
    'emergency_contacts' => [
        'emergency_911' => [
            'label' => 'Emergency',
            'number' => '911',
            'icon' => 'bi-telephone-fill',
            'icon_color' => 'text-danger',
            'description' => 'Life-threatening emergencies only'
        ],
        'school_safety' => [
            'label' => 'School Safety Office',
            'number' => '(903) 763-5511',
            'icon' => 'bi-building',
            'icon_color' => 'text-primary',
            'description' => 'Report non-emergency safety concerns'
        ],
        'school_main' => [
            'label' => 'Main Office',
            'number' => '(903) 664-2961',
            'icon' => 'bi-info-circle',
            'icon_color' => 'text-muted',
            'description' => 'General inquiries'
        ]
    ],
    
    // Form behavior
    'allow_anonymous' => true,
    'require_confirmation' => false,
    'auto_notify_staff' => true,
    
    // Notification settings
    'notify_roles' => ['principal', 'counselor'],
    'notify_email' => 'safety@woodsonisd.net',
    
    // Display options
    'show_help_resources' => true,
    'show_confidentiality_notice' => true,
    
    // Data retention
    'retention_days' => 1825, // 5 years
];
