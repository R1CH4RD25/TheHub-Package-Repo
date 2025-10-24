#!/usr/bin/env php
<?php
/**
 * Environment File Migration Script
 * 
 * This script helps migrate existing .env files to the new structure
 * by adding missing variables while preserving existing values.
 * 
 * Usage: php cli/migrate-env.php [--dry-run]
 */

$dryRun = in_array('--dry-run', $argv);

$envPath = __DIR__ . '/../.env';
$examplePath = __DIR__ . '/../.env.example';

if (!file_exists($envPath)) {
    echo "❌ .env file not found at: {$envPath}\n";
    echo "💡 Copy .env.example to .env first:\n";
    echo "   cp .env.example .env\n";
    exit(1);
}

if (!file_exists($examplePath)) {
    echo "❌ .env.example file not found at: {$examplePath}\n";
    exit(1);
}

echo "🔍 Analyzing .env file...\n\n";

// Parse current .env
$currentEnv = parseEnvFile($envPath);
$exampleEnv = parseEnvFile($examplePath);

// Find missing variables
$missingVars = [];
foreach ($exampleEnv as $key => $exampleValue) {
    if (!isset($currentEnv[$key])) {
        $missingVars[$key] = $exampleValue;
    }
}

// Find deprecated/renamed variables
$deprecatedVars = [
    'MAIL_FROM_ADDRESS' => 'SMTP_FROM_EMAIL',
    'MAIL_HOST' => 'SMTP_HOST',
    'MAIL_PORT' => 'SMTP_PORT',
    'MAIL_USERNAME' => 'SMTP_USERNAME',
    'MAIL_PASSWORD' => 'SMTP_PASSWORD',
    'MAIL_ENCRYPTION' => 'SMTP_ENCRYPTION',
    'DB_PASS' => 'DB_PASSWORD'
];

$migratedVars = [];
foreach ($deprecatedVars as $old => $new) {
    if (isset($currentEnv[$old]) && !isset($currentEnv[$new])) {
        $migratedVars[$new] = $currentEnv[$old];
        echo "📝 Will migrate {$old} → {$new}\n";
    }
}

// Report findings
if (empty($missingVars) && empty($migratedVars)) {
    echo "✅ Your .env file is up to date!\n";
    echo "   No missing variables found.\n";
    exit(0);
}

echo "📊 Found " . count($missingVars) . " missing variables:\n";
foreach ($missingVars as $key => $value) {
    echo "   • {$key}\n";
}

if (!empty($migratedVars)) {
    echo "\n📦 Found " . count($migratedVars) . " variables to migrate:\n";
    foreach ($migratedVars as $new => $value) {
        echo "   • {$new}\n";
    }
}

if ($dryRun) {
    echo "\n🔍 DRY RUN - No changes made\n";
    echo "\nTo apply changes, run without --dry-run flag:\n";
    echo "   php cli/migrate-env.php\n";
    exit(0);
}

// Backup current .env
$backupPath = $envPath . '.backup-' . date('Y-m-d-His');
copy($envPath, $backupPath);
echo "\n💾 Backed up current .env to: {$backupPath}\n";

// Read current .env file lines
$lines = file($envPath, FILE_IGNORE_NEW_LINES);
$newLines = [];
$addedVars = [];

// Process existing lines, adding new vars in appropriate sections
$currentSection = '';
$sectionMarkers = [
    'DATABASE CONFIGURATION' => 'database',
    'APPLICATION SETTINGS' => 'app',
    'AUTHENTICATION & LOGIN' => 'auth',
    'GOOGLE OAUTH CONFIGURATION' => 'google_oauth',
    'MICROSOFT OAUTH CONFIGURATION' => 'microsoft_oauth',
    'GOOGLE WORKSPACE GROUPS' => 'google_groups',
    'EMAIL CONFIGURATION' => 'email',
    'SECURITY' => 'security',
    'SUPER ADMIN' => 'admin'
];

$varSections = [
    // Database
    'DB_HOST' => 'database',
    'DB_NAME' => 'database',
    'DB_USER' => 'database',
    'DB_PASSWORD' => 'database',
    'DB_PASS' => 'database',
    
    // Application
    'APP_URL' => 'app',
    'APP_ENV' => 'app',
    'DEBUG_MODE' => 'app',
    'MAX_UPLOAD_SIZE' => 'app',
    'MAINTENANCE_MODE' => 'app',
    
    // Auth
    'GOOGLE_ONLY_LOGIN' => 'auth',
    'ALLOW_LOCAL_USERS' => 'auth',
    'REQUIRE_DOMAIN_MATCH' => 'auth',
    'ALLOWED_DOMAINS' => 'auth',
    'SESSION_TIMEOUT' => 'auth',
    
    // Google OAuth
    'GOOGLE_CLIENT_ID' => 'google_oauth',
    'GOOGLE_CLIENT_SECRET' => 'google_oauth',
    'GOOGLE_REDIRECT_URI' => 'google_oauth',
    
    // Microsoft OAuth
    'ENABLE_MICROSOFT_LOGIN' => 'microsoft_oauth',
    'MICROSOFT_CLIENT_ID' => 'microsoft_oauth',
    'MICROSOFT_CLIENT_SECRET' => 'microsoft_oauth',
    'MICROSOFT_TENANT_ID' => 'microsoft_oauth',
    'MICROSOFT_REDIRECT_URI' => 'microsoft_oauth',
    
    // Google Groups
    'ENABLE_GOOGLE_GROUPS' => 'google_groups',
    'GOOGLE_SERVICE_ACCOUNT_EMAIL' => 'google_groups',
    'GOOGLE_ADMIN_EMAIL' => 'google_groups',
    'GOOGLE_SERVICE_ACCOUNT_JSON' => 'google_groups',
    'GOOGLE_AUTO_APPROVAL_GROUPS' => 'google_groups',
    'GOOGLE_GROUP_ROLE_ASSOCIATIONS' => 'google_groups',
    'STAFF_GROUP_EMAIL' => 'google_groups',
    
    // Email
    'SMTP_HOST' => 'email',
    'SMTP_PORT' => 'email',
    'SMTP_USERNAME' => 'email',
    'SMTP_PASSWORD' => 'email',
    'SMTP_FROM_EMAIL' => 'email',
    'SMTP_FROM_NAME' => 'email',
    'SMTP_ENCRYPTION' => 'email',
    'MAIL_FROM_ADDRESS' => 'email',
    'MAIL_HOST' => 'email',
    'MAIL_PORT' => 'email',
    'MAIL_USERNAME' => 'email',
    'MAIL_PASSWORD' => 'email',
    'MAIL_ENCRYPTION' => 'email',
    
    // Security
    'SESSION_SECRET' => 'security',
    'SESSION_LIFETIME' => 'security',
    
    // Admin
    'SUPER_ADMIN_EMAIL' => 'admin'
];

$sectionsWithVars = [];

foreach ($lines as $line) {
    $trimmed = trim($line);
    
    // Track current section
    foreach ($sectionMarkers as $marker => $section) {
        if (strpos($trimmed, $marker) !== false) {
            $currentSection = $section;
            break;
        }
    }
    
    // Keep the line
    $newLines[] = $line;
    
    // Check if this is a variable line
    if (!empty($trimmed) && strpos($trimmed, '#') !== 0 && strpos($line, '=') !== false) {
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        
        if (!empty($currentSection)) {
            $sectionsWithVars[$currentSection] = true;
        }
        
        // After each variable, check if we should add missing vars for this section
        if (isset($varSections[$key]) && $varSections[$key] === $currentSection) {
            // Add any missing vars that belong to this section
            foreach ($missingVars as $missingKey => $missingValue) {
                if (!in_array($missingKey, $addedVars) && 
                    isset($varSections[$missingKey]) && 
                    $varSections[$missingKey] === $currentSection) {
                    $newLines[] = "{$missingKey}={$missingValue}";
                    $addedVars[] = $missingKey;
                }
            }
            
            // Add migrated vars to this section
            foreach ($migratedVars as $migratedKey => $migratedValue) {
                if (!in_array($migratedKey, $addedVars) && 
                    isset($varSections[$migratedKey]) && 
                    $varSections[$migratedKey] === $currentSection) {
                    $newLines[] = "{$migratedKey}={$migratedValue}";
                    $addedVars[] = $migratedKey;
                }
            }
        }
    }
}

// Add any remaining missing vars at the end
foreach ($missingVars as $key => $value) {
    if (!in_array($key, $addedVars)) {
        $newLines[] = "{$key}={$value}";
        $addedVars[] = $key;
    }
}

// Write updated .env
file_put_contents($envPath, implode("\n", $newLines) . "\n");

echo "\n✅ Migration complete!\n";
echo "   Added " . count($addedVars) . " new variables\n";
echo "\n📝 Changes made:\n";
foreach ($addedVars as $key) {
    echo "   ✓ {$key}\n";
}

echo "\n💡 Next steps:\n";
echo "   1. Review your .env file: nano .env\n";
echo "   2. Update any placeholder values (your-app-id-here, etc.)\n";
echo "   3. Configure settings via Admin Panel → Site Settings → Advanced\n";
echo "\n";

/**
 * Parse .env file into key-value array
 */
function parseEnvFile(string $path): array {
    $result = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        $trimmed = trim($line);
        
        // Skip comments
        if (strpos($trimmed, '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes
            if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                $value = $matches[2];
            }
            
            $result[$key] = $value;
        }
    }
    
    return $result;
}
