<?php
require_once __DIR__ . '/../../src/bootstrap.php';

Hub\Auth::requireLogin();

$action = $_GET['action'] ?? '';

// Get current user ID
$userId = $_SESSION['user_id'];

switch ($action) {
    case 'check':
        // Check if user has dismissed package alerts
        $db = Hub\Database::getInstance();
        
        // Count packages needing validation (excluding dismissed ones)
        $pendingPackages = $db->fetchAll(
            "SELECT sp.id, sp.display_name 
             FROM section_packages sp
             LEFT JOIN user_dismissed_alerts uda ON (
                 uda.user_id = ? 
                 AND uda.alert_type = 'package_validation' 
                 AND (uda.alert_key = CONCAT('package_', sp.id) OR uda.alert_key = 'all')
             )
             WHERE sp.is_deprecated = 0 
             AND (sp.validation_status IS NULL OR sp.validation_status = 'pending')
             AND uda.id IS NULL
             LIMIT 10",
            [$userId]
        );
        
        // Count installed packages (validated and can_install = 1)
        $installedResult = $db->fetchOne(
            "SELECT COUNT(*) as count FROM section_packages 
             WHERE is_deprecated = 0 
             AND validation_status = 'valid' 
             AND can_install = 1"
        );
        $installedCount = $installedResult['count'] ?? 0;
        
        // Count available updates (for now, just count deprecated as needing updates)
        $updateResult = $db->fetchOne(
            "SELECT COUNT(*) as count FROM section_packages 
             WHERE is_deprecated = 1"
        );
        $updateCount = $updateResult['count'] ?? 0;
        
        // Check if user has dismissed these alerts
        $dismissedValidation = $db->fetchOne(
            "SELECT id FROM user_dismissed_alerts 
             WHERE user_id = ? AND alert_type = 'package_validation' 
             AND dismissed_at > DATE_SUB(NOW(), INTERVAL 7 DAY)",
            [$userId]
        );
        
        $dismissedUpdates = $db->fetchOne(
            "SELECT id FROM user_dismissed_alerts 
             WHERE user_id = ? AND alert_type = 'package_updates' 
             AND dismissed_at > DATE_SUB(NOW(), INTERVAL 7 DAY)",
            [$userId]
        );
        
        $response = [
            'success' => true,
            'alerts' => [
                'validation' => [
                    'count' => count($pendingPackages),
                    'packages' => $pendingPackages,
                    'dismissed' => !empty($dismissedValidation)
                ],
                'installed' => [
                    'count' => $installedCount
                ],
                'updates' => [
                    'count' => $updateCount,
                    'dismissed' => !empty($dismissedUpdates)
                ]
            ]
        ];
        
        jsonResponse($response);
        break;
        
    case 'dismiss':
        verifyCsrfToken($_POST['csrf_token'] ?? '');
        
        $alertType = $_POST['alert_type'] ?? '';
        $packageId = $_POST['package_id'] ?? null; // Optional: specific package to dismiss
        
        if (!in_array($alertType, ['package_validation', 'package_updates'])) {
            jsonResponse(['success' => false, 'error' => 'Invalid alert type'], 400);
        }
        
        $db = Hub\Database::getInstance();
        
        // Use package_id as alert_key if provided, otherwise 'all'
        $alertKey = $packageId ? "package_{$packageId}" : 'all';
        
        // Insert or update dismissed alert (never expires - permanent dismissal)
        $db->execute(
            "INSERT INTO user_dismissed_alerts (user_id, alert_type, alert_key, dismissed_at) 
             VALUES (?, ?, ?, NOW()) 
             ON DUPLICATE KEY UPDATE dismissed_at = NOW()",
            [$userId, $alertType, $alertKey]
        );
        
        jsonResponse([
            'success' => true,
            'message' => 'Alert dismissed for 7 days'
        ]);
        break;
        
    default:
        jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
}
