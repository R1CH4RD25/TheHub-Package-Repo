<?php

namespace Hub\Modules;

/**
 * ModuleInterface - Base interface for all module types
 * 
 * All module renderers must implement this interface to ensure
 * consistent API across different module types.
 * 
 * @author The Hub Team
 * @version 1.0.0
 */
interface ModuleInterface
{
    /**
     * Render the module HTML
     * 
     * @return string HTML content
     */
    public function render(): string;
    
    /**
     * Handle form submission or action request
     * 
     * @param array $data Input data
     * @return array Result with success/error
     */
    public function handle(array $data): array;
    
    /**
     * Get module configuration
     * 
     * @return array Module config
     */
    public function getConfig(): array;
    
    /**
     * Validate module configuration
     * 
     * @return bool True if valid
     */
    public function validate(): bool;
}
