<?php
require_once '../../src/bootstrap.php';

Hub\Auth::requireLogin();

$action = $_GET['action'] ?? '';

// Get current user ID
$userId = $_SESSION['user_id'];

switch ($action) {
    case 'check':
        // Check if user has dismissed package alerts
        $db = Hub\Database::getInstance();
        
        // Count packages needing validation
        $pendingPackages = $db->fetchAll(
            "SELECT id, display_name FROM section_packages 
             WHERE is_active = 1 
             AND (validation_status IS NULL OR validation_status = 'pending')
             LIMIT 10"
        );
        
        // Count available updates
        $updateResult = $db->fetchOne(
            "SELECT COUNT(*) as count FROM section_packages 
             WHERE is_active = 1"
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
        
        jsonResponse([
            'success' => true,
            'alerts' => [
                'validation' => [
                    'count' => count($pendingPackages),
                    'packages' => $pendingPackages,
                    'dismissed' => !empty($dismissedValidation)
                ],
                'updates' => [
                    'count' => $updateCount,
                    'dismissed' => !empty($dismissedUpdates)
                ]
            ]
        ]);
        break;
        
    case 'dismiss':
        verifyCsrfToken();
        
        $alertType = $_POST['alert_type'] ?? '';
        
        if (!in_array($alertType, ['package_validation', 'package_updates'])) {
            jsonResponse(['success' => false, 'error' => 'Invalid alert type'], 400);
        }
        
        $db = Hub\Database::getInstance();
        
        // Insert or update dismissed alert
        $db->execute(
            "INSERT INTO user_dismissed_alerts (user_id, alert_type, alert_key, dismissed_at) 
             VALUES (?, ?, 'all', NOW()) 
             ON DUPLICATE KEY UPDATE dismissed_at = NOW()",
            [$userId, $alertType]
        );
        
        jsonResponse([
            'success' => true,
            'message' => 'Alert dismissed for 7 days'
        ]);
        break;
        
    default:
        jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
}
