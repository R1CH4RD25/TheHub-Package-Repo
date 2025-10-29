<?php
/**
 * Profile API
 * Handle user profile updates
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\Database;
use Hub\AuditLogger;

header('Content-Type: application/json');

Auth::requireLogin();

$currentUser = Auth::getCurrentUser();
$db = Database::getInstance()->getConnection();

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['error' => 'Invalid CSRF token'], 403);
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_contact') {
        try {
            $phone = $_POST['phone'] ?? null;
            $altEmail = $_POST['alt_email'] ?? null;
            $preferredMethod = $_POST['preferred_contact_method'] ?? 'email';
            
            // Validate email if provided
            if ($altEmail && !filter_var($altEmail, FILTER_VALIDATE_EMAIL)) {
                jsonResponse(['error' => 'Invalid alternative email address'], 400);
            }
            
            // Validate preferred method
            $validMethods = ['email', 'alt_email', 'sms', 'email_only', 'sms_only'];
            if (!in_array($preferredMethod, $validMethods)) {
                jsonResponse(['error' => 'Invalid contact method'], 400);
            }
            
            // Get old values for audit log
            $stmt = $db->prepare("SELECT phone, alt_email, preferred_contact_method FROM users WHERE id = :id");
            $stmt->execute(['id' => $currentUser['id']]);
            $oldValues = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Update user profile
            $sql = "UPDATE users 
                    SET phone = :phone,
                        alt_email = :alt_email,
                        preferred_contact_method = :preferred_method
                    WHERE id = :id";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                'phone' => $phone ?: null,
                'alt_email' => $altEmail ?: null,
                'preferred_method' => $preferredMethod,
                'id' => $currentUser['id']
            ]);
            
            // Log the update
            $auditLogger = new AuditLogger();
            $auditLogger->log(
                $currentUser['id'],
                'profile_update',
                $currentUser['id'],
                'users',
                $oldValues,
                [
                    'phone' => $phone,
                    'alt_email' => $altEmail,
                    'preferred_contact_method' => $preferredMethod
                ]
            );
            
            jsonResponse([
                'success' => true,
                'message' => 'Contact preferences updated successfully'
            ]);
            
        } catch (Exception $e) {
            error_log("Profile update error: " . $e->getMessage());
            jsonResponse(['error' => 'Failed to update profile'], 500);
        }
    } else {
        jsonResponse(['error' => 'Invalid action'], 400);
    }
}

// Invalid request method
jsonResponse(['error' => 'Method not allowed'], 405);
