#!/usr/bin/env php
<?php
/**
 * Initial Setup Script for The Hub
 * Creates first super admin user for new installations
 */

require_once __DIR__ . '/../src/bootstrap.php';

use Hub\Database;
use Hub\User;

echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "  The Hub - Initial Setup\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Check if database is accessible
try {
    $db = Database::getInstance();
    echo "✓ Database connection successful\n\n";
} catch (Exception $e) {
    echo "✗ Database connection failed!\n";
    echo "  Error: " . $e->getMessage() . "\n\n";
    echo "  Please check your .env file:\n";
    echo "  - DB_HOST\n";
    echo "  - DB_NAME\n";
    echo "  - DB_USER\n";
    echo "  - DB_PASSWORD\n\n";
    exit(1);
}

// Check if migrations have been run
try {
    $stmt = $db->query("SELECT COUNT(*) FROM users");
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        echo "ℹ  Found existing users in database ($count total)\n\n";
        
        // Check for super admins
        $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND is_active = 1");
        $superAdminCount = $stmt->fetchColumn();
        
        if ($superAdminCount > 0) {
            echo "✓ Found $superAdminCount active super admin(s)\n";
            echo "  No setup needed - system is ready!\n\n";
            exit(0);
        } else {
            echo "⚠  No active super admin found!\n";
            echo "  Continuing with setup...\n\n";
        }
    }
} catch (PDOException $e) {
    echo "✗ Database tables not found!\n";
    echo "  Please run migrations first:\n";
    echo "    php cli/migrate.php\n";
    echo "    php cli/migrate-modules.php\n";
    echo "    php cli/migrate-sections.php\n\n";
    exit(1);
}

echo "───────────────────────────────────────────────────────────\n";
echo "  Create Super Admin Account\n";
echo "───────────────────────────────────────────────────────────\n\n";

// Get super admin details
echo "This account will have full system access.\n\n";

// Email
$email = readline("Email address: ");
$email = trim($email);

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "\n✗ Invalid email address\n\n";
    exit(1);
}

// Check if email already exists
$stmt = $db->prepare("SELECT id, role FROM users WHERE email = ?");
$stmt->execute([$email]);
$existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existingUser) {
    echo "\n⚠  User with this email already exists!\n";
    echo "  Current role: " . $existingUser['role'] . "\n\n";
    
    $upgrade = strtolower(trim(readline("Upgrade to super_admin? (yes/no): ")));
    if ($upgrade !== 'yes' && $upgrade !== 'y') {
        echo "\n✗ Setup cancelled\n\n";
        exit(0);
    }
    
    $stmt = $db->prepare("UPDATE users SET role = 'super_admin', is_active = 1, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$existingUser['id']]);
    
    echo "\n✓ User upgraded to super_admin\n\n";
    exit(0);
}

// Name
$name = readline("Full name: ");
$name = trim($name);

if (empty($name)) {
    echo "\n✗ Name is required\n\n";
    exit(1);
}

// Username (for local login)
$username = readline("Username (for local login): ");
$username = trim($username);

if (empty($username)) {
    echo "\n✗ Username is required\n\n";
    exit(1);
}

// Check if username already exists
$stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
$stmt->execute([$username]);
if ($stmt->fetch()) {
    echo "\n✗ Username already exists\n\n";
    exit(1);
}

// Password
echo "\nPassword requirements:\n";
echo "  - Minimum 8 characters\n";
echo "  - At least one uppercase letter\n";
echo "  - At least one lowercase letter\n";
echo "  - At least one number\n\n";

function readPassword($prompt) {
    echo $prompt;
    system('stty -echo');
    $password = trim(fgets(STDIN));
    system('stty echo');
    echo "\n";
    return $password;
}

$password = readPassword("Password: ");

// Validate password
if (strlen($password) < 8) {
    echo "✗ Password must be at least 8 characters\n\n";
    exit(1);
}
if (!preg_match('/[A-Z]/', $password)) {
    echo "✗ Password must contain at least one uppercase letter\n\n";
    exit(1);
}
if (!preg_match('/[a-z]/', $password)) {
    echo "✗ Password must contain at least one lowercase letter\n\n";
    exit(1);
}
if (!preg_match('/[0-9]/', $password)) {
    echo "✗ Password must contain at least one number\n\n";
    exit(1);
}

$passwordConfirm = readPassword("Confirm password: ");

if ($password !== $passwordConfirm) {
    echo "✗ Passwords do not match\n\n";
    exit(1);
}

// Hash password
$passwordHash = password_hash($password, PASSWORD_BCRYPT);

// Create user
try {
    $stmt = $db->prepare("
        INSERT INTO users (email, username, password, name, role, is_active, created_at)
        VALUES (?, ?, ?, ?, 'super_admin', 1, NOW())
    ");
    
    $stmt->execute([$email, $username, $passwordHash, $name]);
    
    echo "\n✓ Super admin account created successfully!\n\n";
    echo "───────────────────────────────────────────────────────────\n";
    echo "  Login Credentials\n";
    echo "───────────────────────────────────────────────────────────\n";
    echo "  Email:    $email\n";
    echo "  Username: $username\n";
    echo "  Password: [hidden]\n";
    echo "  Role:     super_admin\n";
    echo "───────────────────────────────────────────────────────────\n\n";
    
    echo "🎉 Setup complete!\n\n";
    echo "Next steps:\n";
    echo "  1. Visit your Hub URL: " . ($_ENV['APP_URL'] ?? 'https://hub.yourdomain.com') . "\n";
    echo "  2. Login with username and password\n";
    echo "  3. Configure site settings in Admin Panel\n";
    echo "  4. (Optional) Enable Google/Microsoft OAuth in Advanced Settings\n\n";
    
    // Update SUPER_ADMIN_EMAIL in .env if needed
    if (isset($_ENV['SUPER_ADMIN_EMAIL']) && $_ENV['SUPER_ADMIN_EMAIL'] !== $email) {
        echo "💡 Tip: Update SUPER_ADMIN_EMAIL in .env to: $email\n\n";
    }
    
} catch (PDOException $e) {
    echo "\n✗ Failed to create user\n";
    echo "  Error: " . $e->getMessage() . "\n\n";
    exit(1);
}
