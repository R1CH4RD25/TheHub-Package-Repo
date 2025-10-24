<?php
/**
 * System Configuration API
 * Handles reading and writing .env file and system-level settings
 * Super Admin only access
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\User;
use Hub\AuditLogger;

// CRITICAL: Super Admin only - NO exceptions!
Auth::requireLogin();
$currentUserId = Auth::getCurrentUserId();
$currentUser = Auth::getCurrentUser();
$auditLogger = new AuditLogger();

// Triple-check: Must be super admin AND must not be in "view as" mode
if ($currentUser['role'] !== 'super_admin') {
    http_response_code(403);
    $auditLogger->log(
        'access_denied',
        'system_config',
        0,
        null,
        ['attempted_by' => $currentUser['email'], 'role' => $currentUser['role']],
        $currentUserId
    );
    jsonResponse(['success' => false, 'message' => 'Super Admin access required']);
}

// If using "view as" feature, block access to system config
if (isset($_SESSION['original_role']) && $_SESSION['original_role'] !== 'super_admin') {
    http_response_code(403);
    $auditLogger->log(
        'access_denied_view_as',
        'system_config',
        0,
        null,
        ['attempted_by' => $currentUser['email']],
        $currentUserId
    );
    jsonResponse(['success' => false, 'message' => 'Cannot access system configuration while using "View As" feature']);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// GET - Read current configuration
if ($method === 'GET' && $action === 'load') {
    try {
        $config = loadEnvironmentConfig();
        
        jsonResponse([
            'success' => true,
            'config' => $config
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        jsonResponse(['success' => false, 'message' => $e->getMessage()]);
    }
}

// POST - Save configuration
if ($method === 'POST' && $action === 'save') {
    try {
        // Verify CSRF token for this critical operation
        if (!isset($_POST['csrf_token']) && !isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            throw new Exception('CSRF token required for system configuration changes');
        }
        
        $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!verifyCsrfToken($csrfToken)) {
            throw new Exception('Invalid CSRF token');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        // DEBUG: Log received input
        error_log('=== SYSTEM CONFIG SAVE DEBUG ===');
        error_log('Full input: ' . print_r($input, true));
        if (isset($input['email'])) {
            error_log('Email section: ' . print_r($input['email'], true));
        }
        error_log('================================');
        
        if (!$input) {
            throw new Exception('Invalid input data');
        }
        
        // Backup current .env before making changes
        backupEnvironmentFile();
        
        // Update .env file
        $updated = updateEnvironmentFile($input);
        
        // Log the change with detailed information
        $auditLogger = new AuditLogger();
        $auditLogger->log(
            'update',
            'system_config',
            0,
            null,
            [
                'message' => 'System configuration updated',
                'fields_updated' => $updated,
                'user_email' => $currentUser['email'],
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ],
            $currentUserId
        );
        
        jsonResponse([
            'success' => true,
            'message' => 'Configuration saved successfully. Some changes may require application restart.',
            'updated' => $updated
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        $auditLogger->log(
            'update_failed',
            'system_config',
            0,
            null,
            ['error' => $e->getMessage()],
            $currentUserId
        );
        jsonResponse(['success' => false, 'message' => $e->getMessage()]);
    }
}

// POST - Test database connection
if ($method === 'POST' && $action === 'test-db') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $host = $input['host'] ?? '';
        $name = $input['name'] ?? '';
        $user = $input['user'] ?? '';
        $pass = $input['password'] ?? '';
        
        // If password is empty or asterisks, use the current .env password
        if (empty($pass) || $pass === '********') {
            $pass = $_ENV['DB_PASSWORD'] ?? '';
        }
        
        if (!$host || !$name || !$user) {
            throw new Exception('Database host, name, and user are required');
        }
        
        if (empty($pass)) {
            throw new Exception('Database password is not configured');
        }
        
        // Attempt connection
        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        // Test a simple query
        $stmt = $pdo->query('SELECT VERSION() as version');
        $result = $stmt->fetch();
        
        jsonResponse([
            'success' => true,
            'message' => 'Database connection successful',
            'database' => $name,
            'version' => $result['version']
        ]);
        
    } catch (PDOException $e) {
        jsonResponse([
            'success' => false,
            'message' => 'Database connection failed: ' . $e->getMessage()
        ]);
    }
}

// POST - Clear all sessions
if ($method === 'POST' && $action === 'clear-sessions') {
    try {
        $sessionPath = __DIR__ . '/../../sessions/';
        $count = 0;
        
        if (is_dir($sessionPath)) {
            $files = glob($sessionPath . 'sess_*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $count++;
                }
            }
        }
        
        $auditLogger->log(
            'clear_sessions',
            'system_config',
            0,
            null,
            ['sessions_cleared' => $count],
            $currentUserId
        );
        
        jsonResponse([
            'success' => true,
            'message' => "{$count} sessions cleared. All users have been logged out.",
            'count' => $count
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        jsonResponse(['success' => false, 'message' => $e->getMessage()]);
    }
}

// POST - Regenerate .env file from database
if ($method === 'POST' && $action === 'regenerate-env') {
    try {
        // Backup first
        backupEnvironmentFile();
        
        // Get current settings from database (for things we store there)
        $db = Database::getInstance();
        $settings = $db->query("SELECT * FROM site_settings WHERE id = 1")->fetch();
        
        // Read current .env for things not in database
        $currentEnv = loadEnvironmentConfig();
        
        // Generate new .env content
        $envContent = generateEnvFileContent($currentEnv, $settings);
        
        // Write to .env
        file_put_contents(__DIR__ . '/../../.env', $envContent);
        
        $auditLogger->log(
            'regenerate_env',
            'system_config',
            0,
            null,
            ['message' => '.env file regenerated'],
            $currentUserId
        );
        
        jsonResponse([
            'success' => true,
            'message' => '.env file regenerated successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        jsonResponse(['success' => false, 'message' => $e->getMessage()]);
    }
}

// POST - Upload service account JSON file
if ($method === 'POST' && $action === 'upload-service-account') {
    try {
        if (!isset($_FILES['serviceAccountFile'])) {
            throw new Exception('No file uploaded');
        }
        
        $file = $_FILES['serviceAccountFile'];
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }
        
        // Check file size (max 1MB for JSON)
        if ($file['size'] > 1048576) {
            throw new Exception('File too large. Service account JSON should be under 1MB.');
        }
        
        // Validate it's valid JSON
        $content = file_get_contents($file['tmp_name']);
        $json = json_decode($content, true);
        
        if (!$json) {
            throw new Exception('Invalid JSON file');
        }
        
        // Validate it's a service account key
        if (!isset($json['type']) || $json['type'] !== 'service_account') {
            throw new Exception('This does not appear to be a service account key file');
        }
        
        if (!isset($json['client_email']) || !isset($json['private_key'])) {
            throw new Exception('Service account key file is missing required fields');
        }
        
        // Generate filename
        $filename = 'service-account-' . time() . '.json';
        $configPath = __DIR__ . '/../../config/';
        
        // Create config directory if it doesn't exist
        if (!is_dir($configPath)) {
            mkdir($configPath, 0750, true);
        }
        
        $targetPath = $configPath . $filename;
        
        // Move file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('Failed to save file');
        }
        
        // Set restrictive permissions
        chmod($targetPath, 0640);
        
        // Log the upload
        $auditLogger->log(
            'upload_service_account',
            'system_config',
            0,
            null,
            [
                'filename' => $filename,
                'service_account_email' => $json['client_email'],
                'project_id' => $json['project_id'] ?? 'unknown'
            ],
            $currentUserId
        );
        
        jsonResponse([
            'success' => true,
            'message' => 'Service account file uploaded successfully',
            'filename' => $filename,
            'path' => 'config/' . $filename,
            'service_account_email' => $json['client_email']
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        jsonResponse(['success' => false, 'message' => $e->getMessage()]);
    }
}

// POST - Test SMTP configuration
if ($method === 'POST' && $action === 'test-smtp') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $host = $input['host'] ?? '';
        $port = $input['port'] ?? 587;
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';
        $testEmail = $input['test_email'] ?? $currentUser['email'];
        
        if (!$host || !$username) {
            throw new Exception('SMTP host and username are required');
        }
        
        // Test SMTP connection
        require_once __DIR__ . '/../../vendor/autoload.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = $port;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->SMTPSecure = $port == 465 ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        
        // Just test connection, don't send
        $mail->SMTPDebug = 0;
        $connected = $mail->smtpConnect();
        
        if (!$connected) {
            throw new Exception('Failed to connect to SMTP server');
        }
        
        $mail->smtpClose();
        
        $auditLogger->log(
            'test_smtp',
            'system_config',
            0,
            null,
            ['host' => $host, 'port' => $port, 'result' => 'success'],
            $currentUserId
        );
        
        jsonResponse([
            'success' => true,
            'message' => "SMTP connection successful! Connected to {$host}:{$port}"
        ]);
        
    } catch (Exception $e) {
        jsonResponse([
            'success' => false,
            'message' => 'SMTP connection failed: ' . $e->getMessage()
        ]);
    }
}

// POST - Send test email
if ($method === 'POST' && $action === 'send-test-email') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $host = $input['host'] ?? '';
        $port = $input['port'] ?? 587;
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';
        $fromEmail = $input['from_email'] ?? '';
        $fromName = $input['from_name'] ?? 'The Hub';
        $testEmail = $input['test_email'] ?? $currentUser['email'];
        
        // If password is empty or asterisks, use the current .env password
        if (empty($password) || $password === '********') {
            $password = $_ENV['SMTP_PASSWORD'] ?? '';
        }
        
        if (!$host || !$username || !$fromEmail || !$testEmail) {
            throw new Exception('SMTP host, username, from email, and test email are required');
        }
        
        if (empty($password)) {
            throw new Exception('SMTP password is not configured');
        }
        
        // Send actual test email
        require_once __DIR__ . '/../../vendor/autoload.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = $port;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->SMTPSecure = $port == 465 ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($testEmail);
        $mail->Subject = 'Test Email from The Hub';
        $mail->isHTML(true);
        $mail->Body = '
            <html>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                    <h2 style="color: #2563EB;">✅ SMTP Configuration Test</h2>
                    <p>This is a test email from <strong>The Hub</strong> system.</p>
                    <p>If you received this email, your SMTP settings are working correctly!</p>
                    <hr style="border: none; border-top: 1px solid #ddd; margin: 20px 0;">
                    <p style="color: #666; font-size: 14px;">
                        <strong>Configuration Details:</strong><br>
                        SMTP Host: ' . htmlspecialchars($host) . '<br>
                        SMTP Port: ' . htmlspecialchars($port) . '<br>
                        From Email: ' . htmlspecialchars($fromEmail) . '<br>
                        From Name: ' . htmlspecialchars($fromName) . '<br>
                        Test sent at: ' . date('Y-m-d H:i:s T') . '
                    </p>
                </div>
            </body>
            </html>
        ';
        $mail->AltBody = "This is a test email from The Hub system.\n\nIf you received this email, your SMTP settings are working correctly!\n\nConfiguration Details:\nSMTP Host: {$host}\nSMTP Port: {$port}\nFrom Email: {$fromEmail}\nFrom Name: {$fromName}\nTest sent at: " . date('Y-m-d H:i:s T');
        
        $mail->send();
        
        $auditLogger->log(
            'send_test_email',
            'system_config',
            0,
            null,
            ['host' => $host, 'port' => $port, 'from_email' => $fromEmail, 'to' => $testEmail, 'result' => 'success'],
            $currentUserId
        );
        
        jsonResponse([
            'success' => true,
            'message' => "✅ Test email sent successfully to {$testEmail}! Check your inbox."
        ]);
        
    } catch (Exception $e) {
        jsonResponse([
            'success' => false,
            'message' => 'Failed to send test email: ' . $e->getMessage()
        ]);
    }
}

// POST - Test Google Groups connection
if ($method === 'POST' && $action === 'test-google-groups') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $keyPath = $input['key_path'] ?? '';
        $adminEmail = $input['admin_email'] ?? '';
        $testGroup = $input['test_group'] ?? '';
        
        if (!$keyPath || !$adminEmail) {
            throw new Exception('Service account key path and admin email are required');
        }
        
        $fullPath = __DIR__ . '/../../' . $keyPath;
        
        if (!file_exists($fullPath)) {
            throw new Exception('Service account key file not found at: ' . $keyPath);
        }
        
        // Test Google Directory API connection
        require_once __DIR__ . '/../../vendor/autoload.php';
        
        $client = new Google\Client();
        $client->setAuthConfig($fullPath);
        $client->setSubject($adminEmail);
        $client->setScopes(['https://www.googleapis.com/auth/admin.directory.group.member.readonly']);
        
        $service = new Google\Service\Directory($client);
        
        // Try to list a group or get user's groups
        if ($testGroup) {
            $members = $service->members->listMembers($testGroup, ['maxResults' => 1]);
            $message = "Successfully connected! Group '{$testGroup}' is accessible.";
        } else {
            // Just test the connection by trying to access the API
            $client->fetchAccessTokenWithAssertion();
            $message = "Successfully connected to Google Workspace Directory API!";
        }
        
        $auditLogger->log(
            'test_google_groups',
            'system_config',
            0,
            null,
            ['admin_email' => $adminEmail, 'result' => 'success'],
            $currentUserId
        );
        
        jsonResponse([
            'success' => true,
            'message' => $message
        ]);
        
    } catch (Exception $e) {
        jsonResponse([
            'success' => false,
            'message' => 'Google Groups test failed: ' . $e->getMessage()
        ]);
    }
}

http_response_code(400);
jsonResponse(['success' => false, 'message' => 'Invalid action']);

/**
 * Load current environment configuration
 */
function loadEnvironmentConfig(): array {
    $envPath = __DIR__ . '/../../.env';
    
    if (!file_exists($envPath)) {
        throw new Exception('.env file not found');
    }
    
    $config = [];
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                $value = $matches[2];
            }
            
            $config[$key] = $value;
        }
    }
    
    // Map to friendly structure for frontend
    return [
        'auth' => [
            'allow_local_users' => ($config['ALLOW_LOCAL_USERS'] ?? 'true') === 'true',
            'require_domain_match' => ($config['REQUIRE_DOMAIN_MATCH'] ?? 'false') === 'true',
            'allowed_domains' => $config['ALLOWED_DOMAINS'] ?? '',
            'session_timeout' => (int)($config['SESSION_TIMEOUT'] ?? 2)
        ],
        'google_oauth' => [
            'enabled' => ($config['ENABLE_GOOGLE_LOGIN'] ?? 'true') === 'true',
            'client_id' => $config['GOOGLE_CLIENT_ID'] ?? '',
            'client_secret' => $config['GOOGLE_CLIENT_SECRET'] ?? '',
            'redirect_uri' => $config['GOOGLE_REDIRECT_URI'] ?? ''
        ],
        'microsoft_oauth' => [
            'enabled' => ($config['ENABLE_MICROSOFT_LOGIN'] ?? 'false') === 'true',
            'client_id' => $config['MICROSOFT_CLIENT_ID'] ?? '',
            'client_secret' => $config['MICROSOFT_CLIENT_SECRET'] ?? '',
            'tenant_id' => $config['MICROSOFT_TENANT_ID'] ?? 'common',
            'redirect_uri' => $config['MICROSOFT_REDIRECT_URI'] ?? ''
        ],
        'google_groups' => [
            'enabled' => ($config['ENABLE_GOOGLE_GROUPS'] ?? 'false') === 'true',
            'service_account_email' => $config['GOOGLE_SERVICE_ACCOUNT_EMAIL'] ?? '',
            'admin_email' => $config['GOOGLE_ADMIN_EMAIL'] ?? '',
            'key_path' => $config['GOOGLE_SERVICE_ACCOUNT_JSON'] ?? '',
            // Parse role associations (format: group@domain.com:role_name|group2@domain.com:role_name)
            'role_associations' => parseRoleAssociations($config['GOOGLE_GROUP_ROLE_ASSOCIATIONS'] ?? '')
        ],
        'database' => [
            'host' => $config['DB_HOST'] ?? 'localhost',
            'name' => $config['DB_NAME'] ?? '',
            'user' => $config['DB_USER'] ?? '',
            'password' => '********' // Never send actual password to frontend
        ],
        'app' => [
            'url' => $config['APP_URL'] ?? '',
            'environment' => $config['APP_ENV'] ?? 'production',
            'debug_mode' => ($config['DEBUG_MODE'] ?? 'false') === 'true',
            'max_upload_size' => (int)($config['MAX_UPLOAD_SIZE'] ?? 10),
            'maintenance_mode' => ($config['MAINTENANCE_MODE'] ?? 'false') === 'true'
        ],
        'email' => [
            'smtp_host' => $config['SMTP_HOST'] ?? '',
            'smtp_port' => (int)($config['SMTP_PORT'] ?? 587),
            'smtp_username' => $config['SMTP_USERNAME'] ?? '',
            'smtp_password' => $config['SMTP_PASSWORD'] ? '********' : '', // Never send actual password
            'from_email' => $config['SMTP_FROM_EMAIL'] ?? '',
            'from_name' => $config['SMTP_FROM_NAME'] ?? ''
        ]
    ];
}

/**
 * Parse role associations from env format
 * Format: group@domain.com:role_name|group2@domain.com:role_name
 */
function parseRoleAssociations(string $associations): array {
    if (empty($associations)) {
        return [];
    }
    
    $result = [];
    $pairs = explode('|', $associations);
    
    foreach ($pairs as $pair) {
        if (strpos($pair, ':') !== false) {
            list($group, $role) = explode(':', $pair, 2);
            $result[] = [
                'group' => trim($group),
                'role' => trim($role) // Keep as-is (might be comma-separated like "staff,manager")
            ];
        }
    }
    
    return $result;
}

/**
 * Backup current .env file
 */
function backupEnvironmentFile(): void {
    $envPath = __DIR__ . '/../../.env';
    $backupPath = __DIR__ . '/../../.env.backup.' . date('Y-m-d_H-i-s');
    
    if (file_exists($envPath)) {
        copy($envPath, $backupPath);
    }
}

/**
 * Update .env file with new values
 */
function updateEnvironmentFile(array $input): array {
    $envPath = __DIR__ . '/../../.env';
    
    // Read current .env
    $lines = file_exists($envPath) ? file($envPath, FILE_IGNORE_NEW_LINES) : [];
    $newLines = [];
    $updatedKeys = [];
    
    // Map frontend structure to .env keys
    $mapping = [
        'auth.allow_local_users' => 'ALLOW_LOCAL_USERS',
        'auth.require_domain_match' => 'REQUIRE_DOMAIN_MATCH',
        'auth.allowed_domains' => 'ALLOWED_DOMAINS',
        'auth.session_timeout' => 'SESSION_TIMEOUT',
        'google_oauth.enabled' => 'ENABLE_GOOGLE_LOGIN',
        'google_oauth.client_id' => 'GOOGLE_CLIENT_ID',
        'google_oauth.client_secret' => 'GOOGLE_CLIENT_SECRET',
        'google_oauth.redirect_uri' => 'GOOGLE_REDIRECT_URI',
        'microsoft_oauth.enabled' => 'ENABLE_MICROSOFT_LOGIN',
        'microsoft_oauth.client_id' => 'MICROSOFT_CLIENT_ID',
        'microsoft_oauth.client_secret' => 'MICROSOFT_CLIENT_SECRET',
        'microsoft_oauth.tenant_id' => 'MICROSOFT_TENANT_ID',
        'microsoft_oauth.redirect_uri' => 'MICROSOFT_REDIRECT_URI',
        'google_groups.enabled' => 'ENABLE_GOOGLE_GROUPS',
        'google_groups.service_account_email' => 'GOOGLE_SERVICE_ACCOUNT_EMAIL',
        'google_groups.admin_email' => 'GOOGLE_ADMIN_EMAIL',
        'google_groups.key_path' => 'GOOGLE_SERVICE_ACCOUNT_JSON',
        'google_groups.role_associations' => 'GOOGLE_GROUP_ROLE_ASSOCIATIONS',
        'database.host' => 'DB_HOST',
        'database.name' => 'DB_NAME',
        'database.user' => 'DB_USER',
        'database.password' => 'DB_PASSWORD',
        'app.url' => 'APP_URL',
        'app.environment' => 'APP_ENV',
        'app.debug_mode' => 'DEBUG_MODE',
        'app.max_upload_size' => 'MAX_UPLOAD_SIZE',
        'app.maintenance_mode' => 'MAINTENANCE_MODE',
        'email.smtp_host' => 'SMTP_HOST',
        'email.smtp_port' => 'SMTP_PORT',
        'email.smtp_username' => 'SMTP_USERNAME',
        'email.smtp_password' => 'SMTP_PASSWORD',
        'email.from_email' => 'SMTP_FROM_EMAIL',
        'email.from_name' => 'SMTP_FROM_NAME'
    ];
    
    // Flatten input structure
    $flatInput = [];
    foreach ($input as $section => $values) {
        foreach ($values as $key => $value) {
            $flatKey = "{$section}.{$key}";
            
            // Handle role associations specially (convert array to pipe-separated string)
            if ($flatKey === 'google_groups.role_associations' && is_array($value)) {
                $pairs = [];
                foreach ($value as $assoc) {
                    if (isset($assoc['group']) && isset($assoc['role']) && !empty($assoc['group'])) {
                        $pairs[] = $assoc['group'] . ':' . $assoc['role'];
                    }
                }
                $value = implode('|', $pairs);
            }
            
            if (isset($mapping[$flatKey])) {
                $envKey = $mapping[$flatKey];
                
                // Skip password fields if they're just asterisks (unchanged)
                if (($key === 'password' || $key === 'smtp_password') && $value === '********') {
                    continue;
                }
                
                // Convert booleans
                if (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                }
                
                $flatInput[$envKey] = $value;
            }
        }
    }
    
    // DEBUG: Log what we're about to save
    error_log('=== FLATTENED INPUT FOR .ENV ===');
    if (isset($flatInput['SMTP_FROM_EMAIL'])) {
        error_log('SMTP_FROM_EMAIL = ' . $flatInput['SMTP_FROM_EMAIL']);
    }
    if (isset($flatInput['SMTP_FROM_NAME'])) {
        error_log('SMTP_FROM_NAME = ' . $flatInput['SMTP_FROM_NAME']);
    }
    error_log('================================');
    
    // Update existing lines
    $processedKeys = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        
        // Keep comments and empty lines
        if (empty($trimmed) || strpos($trimmed, '#') === 0) {
            $newLines[] = $line;
            continue;
        }
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $oldValue) = explode('=', $line, 2);
            $key = trim($key);
            
            if (isset($flatInput[$key])) {
                // Update this value
                $newValue = $flatInput[$key];
                
                // Quote values with spaces
                if (strpos($newValue, ' ') !== false) {
                    $newValue = '"' . $newValue . '"';
                }
                
                $newLines[] = "{$key}={$newValue}";
                $processedKeys[] = $key;
                $updatedKeys[] = $key;
            } else {
                // Keep unchanged
                $newLines[] = $line;
                $processedKeys[] = $key;
            }
        } else {
            $newLines[] = $line;
        }
    }
    
    // Add any new keys that weren't in the file
    foreach ($flatInput as $key => $value) {
        if (!in_array($key, $processedKeys)) {
            if (strpos($value, ' ') !== false) {
                $value = '"' . $value . '"';
            }
            $newLines[] = "{$key}={$value}";
            $updatedKeys[] = $key;
        }
    }
    
    // Write back to file
    file_put_contents($envPath, implode("\n", $newLines) . "\n");
    
    return $updatedKeys;
}

/**
 * Generate complete .env file content
 */
function generateEnvFileContent(array $currentEnv, ?array $dbSettings): string {
    $content = "# The Hub - Environment Configuration\n";
    $content .= "# Generated: " . date('Y-m-d H:i:s') . "\n\n";
    
    $content .= "# Database\n";
    $content .= "DB_HOST=" . ($currentEnv['DB_HOST'] ?? 'localhost') . "\n";
    $content .= "DB_NAME=" . ($currentEnv['DB_NAME'] ?? 'thehub') . "\n";
    $content .= "DB_USER=" . ($currentEnv['DB_USER'] ?? '') . "\n";
    $content .= "DB_PASSWORD=" . ($currentEnv['DB_PASSWORD'] ?? '') . "\n\n";
    
    $content .= "# Application\n";
    $content .= "APP_URL=" . ($currentEnv['APP_URL'] ?? '') . "\n";
    $content .= "APP_ENV=" . ($currentEnv['APP_ENV'] ?? 'production') . "\n";
    $content .= "DEBUG_MODE=" . ($currentEnv['DEBUG_MODE'] ?? 'false') . "\n";
    $content .= "MAX_UPLOAD_SIZE=" . ($currentEnv['MAX_UPLOAD_SIZE'] ?? '10') . "\n";
    $content .= "MAINTENANCE_MODE=" . ($currentEnv['MAINTENANCE_MODE'] ?? 'false') . "\n\n";
    
    $content .= "# Authentication\n";
    $content .= "ALLOW_LOCAL_USERS=" . ($currentEnv['ALLOW_LOCAL_USERS'] ?? 'true') . "\n";
    $content .= "REQUIRE_DOMAIN_MATCH=" . ($currentEnv['REQUIRE_DOMAIN_MATCH'] ?? 'false') . "\n";
    $content .= "ALLOWED_DOMAINS=" . ($currentEnv['ALLOWED_DOMAINS'] ?? '') . "\n";
    $content .= "SESSION_TIMEOUT=" . ($currentEnv['SESSION_TIMEOUT'] ?? '2') . "\n\n";
    
    $content .= "# Google OAuth\n";
    $content .= "GOOGLE_CLIENT_ID=" . ($currentEnv['GOOGLE_CLIENT_ID'] ?? '') . "\n";
    $content .= "GOOGLE_CLIENT_SECRET=" . ($currentEnv['GOOGLE_CLIENT_SECRET'] ?? '') . "\n";
    $content .= "GOOGLE_REDIRECT_URI=" . ($currentEnv['GOOGLE_REDIRECT_URI'] ?? '') . "\n\n";
    
    $content .= "# Google Workspace Groups\n";
    $content .= "ENABLE_GOOGLE_GROUPS=" . ($currentEnv['ENABLE_GOOGLE_GROUPS'] ?? 'false') . "\n";
    $content .= "GOOGLE_SERVICE_ACCOUNT_EMAIL=" . ($currentEnv['GOOGLE_SERVICE_ACCOUNT_EMAIL'] ?? '') . "\n";
    $content .= "GOOGLE_ADMIN_EMAIL=" . ($currentEnv['GOOGLE_ADMIN_EMAIL'] ?? '') . "\n";
    $content .= "GOOGLE_SERVICE_ACCOUNT_JSON=" . ($currentEnv['GOOGLE_SERVICE_ACCOUNT_JSON'] ?? '') . "\n";
    $content .= "# Role associations: group@domain.com:role_name|group2@domain.com:role_name\n";
    $content .= "# Available roles: viewer, user, driver, admin, super_admin\n";
    $content .= "GOOGLE_GROUP_ROLE_ASSOCIATIONS=" . ($currentEnv['GOOGLE_GROUP_ROLE_ASSOCIATIONS'] ?? '') . "\n\n";
    
    $content .= "# Email (SMTP)\n";
    $content .= "SMTP_HOST=" . ($currentEnv['SMTP_HOST'] ?? '') . "\n";
    $content .= "SMTP_PORT=" . ($currentEnv['SMTP_PORT'] ?? '587') . "\n";
    $content .= "SMTP_USERNAME=" . ($currentEnv['SMTP_USERNAME'] ?? '') . "\n";
    $content .= "SMTP_PASSWORD=" . ($currentEnv['SMTP_PASSWORD'] ?? '') . "\n";
    $content .= "SMTP_FROM_EMAIL=" . ($currentEnv['SMTP_FROM_EMAIL'] ?? '') . "\n";
    $content .= "SMTP_FROM_NAME=" . ($currentEnv['SMTP_FROM_NAME'] ?? 'The Hub') . "\n";
    
    return $content;
}
