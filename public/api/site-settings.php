<?php
/**
 * Site Settings API
 * Manage dynamic site configuration (branding, colors, settings)
 * Super Admin Only
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\Database;
use Hub\AuditLogger;

header('Content-Type: application/json');

// Super Admin only
Auth::requireRole(['super_admin']);

$db = Database::getInstance();
$currentUser = Auth::getCurrentUser();

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // Get all settings
            $settings = $db->fetchAll("
                SELECT setting_key, setting_value, setting_type, description 
                FROM site_settings 
                ORDER BY setting_key
            ");
            
            // Convert to key-value object for easier frontend use
            $settingsObject = [];
            foreach ($settings as $setting) {
                $value = $setting['setting_value'];
                
                // Type conversion
                if ($setting['setting_type'] === 'boolean') {
                    $value = (bool)$value;
                } elseif ($setting['setting_type'] === 'number') {
                    $value = is_numeric($value) ? (int)$value : $value;
                }
                
                $settingsObject[$setting['setting_key']] = $value;
            }
            
            jsonResponse($settingsObject);
            break;
            
        case 'POST':
            // Update settings
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data || !is_array($data)) {
                jsonResponse(['error' => 'Invalid request data'], 400);
            }
            
            // Verify CSRF token if present
            if (isset($data['csrf_token'])) {
                if (!verifyCsrfToken($data['csrf_token'])) {
                    jsonResponse(['error' => 'Invalid CSRF token'], 403);
                }
                unset($data['csrf_token']);
            }
            
            $updatedCount = 0;
            $errors = [];
            
            foreach ($data as $key => $value) {
                // Validate setting exists
                $existing = $db->fetchOne("
                    SELECT id, setting_key, setting_type, setting_value 
                    FROM site_settings 
                    WHERE setting_key = ?
                ", [$key]);
                
                if (!$existing) {
                    $errors[] = "Unknown setting: $key";
                    continue;
                }
                
                // Type conversion for storage
                if ($existing['setting_type'] === 'boolean') {
                    $value = $value ? '1' : '0';
                }
                
                // Update setting
                $db->execute("
                    UPDATE site_settings 
                    SET setting_value = ?, updated_by = ? 
                    WHERE setting_key = ?
                ", [$value, $currentUser['id'], $key]);
                
                $updatedCount++;
                
                // Audit log with proper record ID
                $auditLogger = new AuditLogger();
                $auditLogger->log(
                    'update',
                    'site_settings',
                    $existing['id'],
                    ['setting_value' => $existing['setting_value']],
                    ['setting_value' => $value],
                    $currentUser['id']
                );
            }
            
            if (!empty($errors)) {
                jsonResponse([
                    'message' => "Updated $updatedCount settings with some errors",
                    'errors' => $errors,
                    'updated' => $updatedCount
                ], 207); // Multi-status
            } else {
                // Rebuild CSS if production mode is enabled
                if (defined('CSS_PRODUCTION_MODE') && CSS_PRODUCTION_MODE === true) {
                    require_once __DIR__ . '/../../src/CSSBuilder.php';
                    \Hub\CSSBuilder::rebuild();
                }
                
                jsonResponse([
                    'message' => "Successfully updated $updatedCount settings",
                    'updated' => $updatedCount
                ]);
            }
            break;
            
        case 'PUT':
            // Reset to defaults
            $db->execute("
                UPDATE site_settings SET 
                    setting_value = CASE setting_key
                        WHEN 'site_name' THEN 'The Hub'
                        WHEN 'organization_name' THEN 'Your Organization'
                        WHEN 'navbar_subtitle' THEN 'The Hub'
                        WHEN 'welcome_message' THEN 'Central hub for student services and resources'
                        WHEN 'primary_color' THEN '#C99700'
                        WHEN 'navbar_color' THEN '#000000'
                        WHEN 'background_color' THEN '#FFFFFF'
                        WHEN 'accent_color' THEN '#FFD700'
                        WHEN 'logo_path' THEN '/assets/images/branding/Branding_NoBG.png'
                        WHEN 'logo_height' THEN '90'
                        WHEN 'logo_height_mobile' THEN '50'
                        WHEN 'favicon_path' THEN '/assets/images/Cowboy_SM_favicon.png'
                        WHEN 'logo_glow_enabled' THEN '1'
                        WHEN 'session_timeout' THEN '2'
                        WHEN 'max_upload_size' THEN '10'
                        WHEN 'maintenance_mode' THEN '0'
                    END,
                    updated_by = ?
            ", [$currentUser['id']]);
            
            AuditLogger::log(
                'update',
                'site_settings',
                null,
                "Reset all settings to defaults",
                $currentUser['id']
            );
            
            jsonResponse(['message' => 'Settings reset to defaults']);
            break;
            
        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
} catch (\Exception $e) {
    error_log("Site Settings API Error: " . $e->getMessage());
    jsonResponse(['error' => 'Internal server error: ' . $e->getMessage()], 500);
}
