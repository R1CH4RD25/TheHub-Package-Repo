<?php

namespace Hub\Modules;

use Exception;

/**
 * ModuleFactory - Create module instances from configuration
 * 
 * Factory pattern for instantiating the correct module renderer
 * based on module type from package manifest.
 * 
 * Usage:
 *   $module = ModuleFactory::create($moduleConfig);
 *   echo $module->render();
 * 
 * @author The Hub Team
 * @version 1.0.0
 */
class ModuleFactory
{
    /**
     * Create module instance from configuration
     * 
     * @param array $config Module configuration from manifest
     * @return ModuleInterface Module instance
     * @throws Exception If module type not supported
     */
    public static function create(array $config): ModuleInterface
    {
        $type = $config['type'] ?? null;
        
        if (!$type) {
            throw new Exception('Module type not specified');
        }
        
        // Map module types to renderer classes
        $renderers = [
            'Form' => FormRenderer::class,
            'TableView' => TableViewRenderer::class,
            'Workflow' => WorkflowRenderer::class,
            'Analytics' => AnalyticsRenderer::class,
            'Dashboard' => DashboardRenderer::class,
            'EmailNotification' => EmailNotificationRenderer::class,
            'PDFGenerator' => PDFGeneratorRenderer::class,
            'Action' => ActionRenderer::class,
            'Computation' => ComputationRenderer::class,
            'FileManager' => FileManagerRenderer::class,
            'Calendar' => CalendarRenderer::class,
            'Kanban' => KanbanRenderer::class,
            // Note: Report renderer pending (final: 12/12 = 100%)
        ];
        
        if (!isset($renderers[$type])) {
            throw new Exception("Unsupported module type: {$type}");
        }
        
        $rendererClass = $renderers[$type];
        
        // Check if renderer class exists
        if (!class_exists($rendererClass)) {
            throw new Exception("Renderer not implemented: {$rendererClass}");
        }
        
        return new $rendererClass($config);
    }
    
    /**
     * Check if module type is supported
     * 
     * @param string $type Module type
     * @return bool True if supported
     */
    public static function isSupported(string $type): bool
    {
        $supported = [
            'Form', 'TableView', 'Workflow', 'Analytics', 'Dashboard',
            'EmailNotification', 'PDFGenerator', 
            'Action', 'Computation', 'FileManager', 'Calendar', 'Kanban'
            // Note: Report pending (11/12)
        ];
        
        return in_array($type, $supported);
    }
    
    /**
     * Get list of all supported module types
     * 
     * @return array Module types
     */
    public static function getSupportedTypes(): array
    {
        return [
            'Form', 'TableView', 'Workflow', 'Analytics', 'Dashboard',
            'EmailNotification', 'PDFGenerator', 
            'Action', 'Computation', 'FileManager', 'Calendar', 'Kanban'
        ];
    }
}
