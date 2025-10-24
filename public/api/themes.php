<?php
require_once __DIR__ . '/../../src/bootstrap.php';

header('Content-Type: application/json');

use Hub\Auth;
use Hub\Theme;
use Hub\AuditLogger;

// Check if user is logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$user = Auth::getCurrentUser();
$effectiveRole = Auth::getEffectiveRole();
if ($effectiveRole !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Super admin access required']);
    exit;
}

$themeManager = new Theme();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Get all themes or single theme
            if (isset($_GET['id'])) {
                $theme = $themeManager->get((int)$_GET['id']);
                if (!$theme) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Theme not found']);
                    exit;
                }
                echo json_encode($theme);
            } elseif (isset($_GET['action']) && $_GET['action'] === 'active') {
                $theme = $themeManager->getActive();
                echo json_encode($theme);
            } elseif (isset($_GET['action']) && $_GET['action'] === 'export' && isset($_GET['id'])) {
                $export = $themeManager->export((int)$_GET['id']);
                echo json_encode($export);
            } else {
                $themes = $themeManager->getAll();
                echo json_encode($themes);
            }
            break;

        case 'POST':
            // Verify CSRF token
            if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid CSRF token']);
                exit;
            }

            $action = $_POST['action'] ?? '';

            if ($action === 'save_current') {
                // Save current settings as new theme
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');

                if (empty($name)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Theme name is required']);
                    exit;
                }

                $theme = $themeManager->saveFromCurrentSettings($name, $description ?: null, $user['id']);
                
                $logger = new AuditLogger();
                $logger->log(
                    'theme_created',
                    'theme',
                    $theme['id'],
                    null,
                    ['name' => $name]
                );

                echo json_encode([
                    'success' => true,
                    'message' => 'Theme saved successfully',
                    'theme' => $theme
                ]);

            } elseif ($action === 'import') {
                // Import theme from JSON
                $themeData = json_decode($_POST['theme_json'] ?? '{}', true);
                
                if (!$themeData) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid theme data']);
                    exit;
                }

                $theme = $themeManager->import($themeData, $user['id']);
                
                $logger = new AuditLogger();
                $logger->log(
                    'theme_imported',
                    'theme',
                    $theme['id'],
                    null,
                    ['name' => $theme['name']]
                );

                echo json_encode([
                    'success' => true,
                    'message' => 'Theme imported successfully',
                    'theme' => $theme
                ]);

            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
            }
            break;

        case 'PUT':
            // Parse PUT data
            parse_str(file_get_contents('php://input'), $_PUT);

            // Verify CSRF token
            if (!verifyCsrfToken($_PUT['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid CSRF token']);
                exit;
            }

            $action = $_PUT['action'] ?? '';
            $id = (int)($_PUT['id'] ?? 0);

            if ($action === 'activate') {
                // Activate theme (applies settings and marks as active)
                $themeBefore = $themeManager->getActive();
                $themeManager->activate($id);
                $themeAfter = $themeManager->get($id);

                $logger = new AuditLogger();
                $logger->log(
                    'theme_activated',
                    'theme',
                    $id,
                    ['active_theme' => $themeBefore['name'] ?? 'None'],
                    ['active_theme' => $themeAfter['name']]
                );

                echo json_encode([
                    'success' => true,
                    'message' => 'Theme activated successfully'
                ]);

            } elseif ($action === 'update') {
                // Update theme with current settings
                $name = trim($_PUT['name'] ?? '');
                $description = trim($_PUT['description'] ?? '');

                $themeBefore = $themeManager->get($id);
                $themeManager->updateFromCurrentSettings(
                    $id,
                    $name ?: null,
                    $description ?: null
                );

                $logger = new AuditLogger();
                $logger->log(
                    'theme_updated',
                    'theme',
                    $id,
                    ['name' => $themeBefore['name']],
                    ['name' => $name ?: $themeBefore['name']]
                );

                echo json_encode([
                    'success' => true,
                    'message' => 'Theme updated successfully'
                ]);

            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
            }
            break;

        case 'DELETE':
            // Parse DELETE data
            parse_str(file_get_contents('php://input'), $_DELETE);

            // Verify CSRF token
            if (!verifyCsrfToken($_DELETE['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid CSRF token']);
                exit;
            }

            $id = (int)($_DELETE['id'] ?? 0);
            $theme = $themeManager->get($id);
            
            if (!$theme) {
                http_response_code(404);
                echo json_encode(['error' => 'Theme not found']);
                exit;
            }

            $themeManager->delete($id);

            $logger = new AuditLogger();
            $logger->log(
                'theme_deleted',
                'theme',
                $id,
                ['name' => $theme['name']],
                null
            );

            echo json_encode([
                'success' => true,
                'message' => 'Theme deleted successfully'
            ]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
