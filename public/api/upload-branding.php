<?php
/**
 * Upload Logo/Favicon API
 * Handle file uploads for branding assets
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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['error' => 'No file uploaded or upload error'], 400);
    }
    
    $file = $_FILES['file'];
    $fileType = $_POST['type'] ?? ''; // 'logo', 'favicon', or 'hub_tile_icon'
    
    if (!in_array($fileType, ['logo', 'favicon', 'hub_tile_icon'])) {
        jsonResponse(['error' => 'Invalid file type. Must be "logo", "favicon", or "hub_tile_icon"'], 400);
    }
    
    // Validate file type
    $allowedMimeTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedMimeTypes)) {
        jsonResponse(['error' => 'Invalid file type. Only PNG, JPG, SVG, and WebP are allowed'], 400);
    }
    
    // Get file extension
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    
    // Generate filename
    if ($fileType === 'logo') {
        $targetDir = __DIR__ . '/../assets/images/branding/';
        $fileName = 'custom_logo_' . time() . '.' . $extension;
        $settingKey = 'logo_path';
    } elseif ($fileType === 'hub_tile_icon') {
        $targetDir = __DIR__ . '/../assets/images/branding/';
        $fileName = 'custom_hub_tile_icon_' . time() . '.' . $extension;
        $settingKey = 'hub_tile_icon_path';
    } else {
        $targetDir = __DIR__ . '/../assets/images/';
        $fileName = 'custom_favicon_' . time() . '.' . $extension;
        $settingKey = 'favicon_path';
    }
    
    // Create directory if it doesn't exist
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    $targetPath = $targetDir . $fileName;
    $webPath = str_replace(__DIR__ . '/..', '', $targetPath);
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        jsonResponse(['error' => 'Failed to move uploaded file'], 500);
    }
    
    // Get old setting value first for audit trail
    $oldSetting = $db->fetchOne("SELECT id, setting_value FROM site_settings WHERE setting_key = ?", [$settingKey]);
    $oldPath = $oldSetting['setting_value'] ?? null;
    $settingId = $oldSetting['id'] ?? null;
    
    // Update database setting
    $db->execute("
        UPDATE site_settings 
        SET setting_value = ?, updated_by = ? 
        WHERE setting_key = ?
    ", [$webPath, $currentUser['id'], $settingKey]);
    
    // If uploading hub tile icon, automatically enable custom icon setting
    if ($fileType === 'hub_tile_icon') {
        $enabledSetting = $db->fetchOne("SELECT id, setting_value FROM site_settings WHERE setting_key = 'hub_tile_icon_custom_enabled'");
        if ($enabledSetting) {
            $db->execute("
                UPDATE site_settings 
                SET setting_value = '1', updated_by = ? 
                WHERE setting_key = 'hub_tile_icon_custom_enabled'
            ", [$currentUser['id']]);
            
            // Audit log for enabling custom icons
            $auditLogger = new AuditLogger();
            $auditLogger->log(
                'update',
                'site_settings',
                $enabledSetting['id'],
                ['setting_value' => $enabledSetting['setting_value']],
                ['setting_value' => '1'],
                $currentUser['id']
            );
        }
    }
    
    // Delete old file if it's a custom upload (not default)
    if ($oldPath && strpos($oldPath, 'custom_') !== false) {
        $oldFilePath = __DIR__ . '/..' . $oldPath;
        if (file_exists($oldFilePath) && $oldFilePath !== $targetPath) {
            @unlink($oldFilePath);
        }
    }
    
    // Audit log (use the setting ID for the record)
    if ($settingId) {
        $auditLogger = new AuditLogger();
        $auditLogger->log(
            'update',
            'site_settings',
            $settingId,
            ['setting_value' => $oldPath],
            ['setting_value' => $webPath],
            $currentUser['id']
        );
    }
    
    $message = ucfirst(str_replace('_', ' ', $fileType)) . ' uploaded successfully';
    if ($fileType === 'hub_tile_icon') {
        $message .= ' and custom icon mode enabled';
    }
    
    jsonResponse([
        'success' => true,
        'message' => $message,
        'path' => $webPath,
        'fileName' => $fileName
    ]);
    
} catch (\Exception $e) {
    error_log("Upload Logo API Error: " . $e->getMessage());
    jsonResponse(['error' => 'Internal server error: ' . $e->getMessage()], 500);
}
