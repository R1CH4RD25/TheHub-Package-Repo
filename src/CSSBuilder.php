<?php
/**
 * CSS Build Helper
 * 
 * Triggers CSS rebuild when site settings or themes change
 */

namespace Hub;

class CSSBuilder
{
    /**
     * Rebuild production CSS files
     * 
     * @return array Result with success status and message
     */
    public static function rebuild()
    {
        $buildScript = __DIR__ . '/../build-css.sh';
        
        if (!file_exists($buildScript)) {
            return [
                'success' => false,
                'message' => 'Build script not found'
            ];
        }
        
        if (!is_executable($buildScript)) {
            chmod($buildScript, 0755);
        }
        
        // Execute build script
        $output = [];
        $returnCode = 0;
        exec("cd " . dirname($buildScript) . " && ./build-css.sh 2>&1", $output, $returnCode);
        
        if ($returnCode === 0) {
            // Get the version number
            $versionFile = __DIR__ . '/../public/assets/css/version.txt';
            $version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : 'unknown';
            
            return [
                'success' => true,
                'message' => 'CSS rebuilt successfully',
                'version' => $version,
                'output' => implode("\n", $output)
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Build failed',
                'output' => implode("\n", $output)
            ];
        }
    }
    
    /**
     * Check if production mode is enabled
     * 
     * @return bool
     */
    public static function isProductionMode()
    {
        return defined('CSS_PRODUCTION_MODE') && CSS_PRODUCTION_MODE === true;
    }
    
    /**
     * Get current CSS version
     * 
     * @return string
     */
    public static function getVersion()
    {
        if (self::isProductionMode()) {
            $versionFile = __DIR__ . '/../public/assets/css/version.txt';
            return file_exists($versionFile) ? trim(file_get_contents($versionFile)) : time();
        }
        return time();
    }
}
