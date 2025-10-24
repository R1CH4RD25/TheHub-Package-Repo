#!/usr/bin/env php
<?php
/**
 * The Hub - Dependency Checker & Auto-Installer
 * Verifies all system and PHP dependencies are installed
 * Offers to install missing packages automatically
 */

echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "  The Hub - Dependency Checker\n";
echo "═══════════════════════════════════════════════════════════\n\n";

$errors = [];
$warnings = [];
$missingPackages = [];
$allGood = true;

// Color codes for terminal output
$RED = "\033[0;31m";
$GREEN = "\033[0;32m";
$YELLOW = "\033[1;33m";
$BLUE = "\033[0;34m";
$NC = "\033[0m"; // No Color

function checkMark($passed) {
    global $GREEN, $RED, $NC;
    return $passed ? "{$GREEN}✓{$NC}" : "{$RED}✗{$NC}";
}

// ============================================
// Check PHP Version
// ============================================
echo "Checking PHP...\n";
echo "───────────────────────────────────────────────────────────\n";

$phpVersion = phpversion();
$phpVersionOk = version_compare($phpVersion, '8.0.0', '>=');
echo checkMark($phpVersionOk) . " PHP Version: $phpVersion ";
if ($phpVersionOk) {
    echo "{$GREEN}(OK){$NC}\n";
} else {
    echo "{$RED}(Minimum: 8.0.0){$NC}\n";
    $errors[] = "PHP 8.0 or higher required. Current: $phpVersion";
    $allGood = false;
}

// ============================================
// Check Required PHP Extensions
// ============================================
echo "\nChecking PHP Extensions...\n";
echo "───────────────────────────────────────────────────────────\n";

$requiredExtensions = [
    'pdo_mysql' => 'Database connectivity (MariaDB/MySQL)',
    'mbstring' => 'Multibyte string handling',
    'xml' => 'XML processing',
    'curl' => 'HTTP requests (OAuth)',
    'zip' => 'Excel export functionality',
    'gd' => 'Image processing (or imagick)',
    'intl' => 'Internationalization',
    'bcmath' => 'Precise decimal calculations',
    'json' => 'JSON handling',
    'openssl' => 'Encryption & HTTPS'
];

foreach ($requiredExtensions as $ext => $desc) {
    $loaded = extension_loaded($ext);
    echo checkMark($loaded) . " $ext - $desc\n";
    
    if (!$loaded) {
        $errors[] = "Missing PHP extension: $ext";
        $missingPackages[] = "php8.1-" . str_replace('_', '', $ext);
        $allGood = false;
    }
}

// ============================================
// Check Composer Dependencies
// ============================================
echo "\nChecking Composer Dependencies...\n";
echo "───────────────────────────────────────────────────────────\n";

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    echo checkMark(true) . " Composer packages installed\n";
    
    // Check specific packages
    $composerLock = __DIR__ . '/../composer.lock';
    if (file_exists($composerLock)) {
        $lock = json_decode(file_get_contents($composerLock), true);
        $packages = array_column($lock['packages'] ?? [], 'name');
        
        $requiredComposerPackages = [
            'vlucas/phpdotenv' => 'Environment configuration',
            'phpoffice/phpspreadsheet' => 'Excel export',
            'phpmailer/phpmailer' => 'Email sending',
            'google/apiclient' => 'Google OAuth'
        ];
        
        foreach ($requiredComposerPackages as $package => $desc) {
            $installed = in_array($package, $packages);
            echo "  " . checkMark($installed) . " $package - $desc\n";
            
            if (!$installed) {
                $warnings[] = "Missing Composer package: $package";
            }
        }
    }
} else {
    echo checkMark(false) . " Composer packages NOT installed\n";
    $warnings[] = "Run 'composer install' to install PHP dependencies";
}

// ============================================
// Check System Commands
// ============================================
echo "\nChecking System Commands...\n";
echo "───────────────────────────────────────────────────────────\n";

$systemCommands = [
    'mysql' => 'MariaDB/MySQL client',
    'git' => 'Version control',
    'composer' => 'PHP dependency manager'
];

foreach ($systemCommands as $cmd => $desc) {
    exec("which $cmd 2>/dev/null", $output, $returnCode);
    $found = ($returnCode === 0);
    echo checkMark($found) . " $cmd - $desc\n";
    
    if (!$found) {
        if ($cmd === 'composer') {
            $warnings[] = "Composer not found. Install: curl -sS https://getcomposer.org/installer | php";
        } else {
            $errors[] = "Missing command: $cmd";
            $allGood = false;
        }
    }
}

// ============================================
// Check Database Service
// ============================================
echo "\nChecking Services...\n";
echo "───────────────────────────────────────────────────────────\n";

// Try to detect if MariaDB/MySQL is running
exec("systemctl is-active mariadb 2>/dev/null", $output, $mariadbRunning);
exec("systemctl is-active mysql 2>/dev/null", $output, $mysqlRunning);

if ($mariadbRunning === 0 || $mysqlRunning === 0) {
    echo checkMark(true) . " Database service (MariaDB/MySQL) is running\n";
} else {
    echo checkMark(false) . " Database service is NOT running\n";
    $warnings[] = "Database service not running. Start with: sudo systemctl start mariadb";
}

// Check web server
exec("systemctl is-active apache2 2>/dev/null", $output, $apacheRunning);
exec("systemctl is-active httpd 2>/dev/null", $output, $httpdRunning);
exec("systemctl is-active nginx 2>/dev/null", $output, $nginxRunning);

if ($apacheRunning === 0 || $httpdRunning === 0 || $nginxRunning === 0) {
    echo checkMark(true) . " Web server is running\n";
} else {
    echo checkMark(false) . " Web server is NOT running\n";
    $warnings[] = "Web server not running. Start with: sudo systemctl start apache2";
}

// ============================================
// Check File Permissions
// ============================================
echo "\nChecking File Permissions...\n";
echo "───────────────────────────────────────────────────────────\n";

$writableDirs = ['logs', 'sessions', 'temp', 'uploads'];
foreach ($writableDirs as $dir) {
    $path = __DIR__ . "/../$dir";
    $writable = is_dir($path) && is_writable($path);
    echo checkMark($writable) . " $dir/ is writable\n";
    
    if (!$writable) {
        $warnings[] = "Directory not writable: $dir/";
    }
}

// Check .env file
$envExists = file_exists(__DIR__ . '/../.env');
echo checkMark($envExists) . " .env file exists\n";
if (!$envExists) {
    $warnings[] = ".env file not found. Copy from .env.example";
}

// ============================================
// Summary
// ============================================
echo "\n═══════════════════════════════════════════════════════════\n";
echo "  Summary\n";
echo "═══════════════════════════════════════════════════════════\n\n";

if ($allGood && count($warnings) === 0) {
    echo "{$GREEN}✅ All dependencies are installed and configured!{$NC}\n\n";
    echo "Next steps:\n";
    echo "  1. Run migrations: php cli/migrate.php\n";
    echo "  2. Create admin: php cli/setup.php\n";
    echo "  3. Start using The Hub!\n\n";
    exit(0);
}

// Show errors
if (count($errors) > 0) {
    echo "{$RED}Errors:{$NC}\n";
    foreach ($errors as $error) {
        echo "  ✗ $error\n";
    }
    echo "\n";
}

// Show warnings
if (count($warnings) > 0) {
    echo "{$YELLOW}Warnings:{$NC}\n";
    foreach ($warnings as $warning) {
        echo "  ⚠ $warning\n";
    }
    echo "\n";
}

// ============================================
// Offer Auto-Fix
// ============================================
if (count($missingPackages) > 0 && posix_geteuid() !== 0) {
    echo "═══════════════════════════════════════════════════════════\n";
    echo "  Auto-Fix Available\n";
    echo "═══════════════════════════════════════════════════════════\n\n";
    
    echo "Missing system packages detected.\n";
    echo "To install them, run this script with sudo:\n\n";
    echo "  {$BLUE}sudo php cli/check-dependencies.php --install{$NC}\n\n";
}

if (count($missingPackages) > 0 && posix_geteuid() === 0 && in_array('--install', $argv)) {
    echo "═══════════════════════════════════════════════════════════\n";
    echo "  Installing Missing Packages\n";
    echo "═══════════════════════════════════════════════════════════\n\n";
    
    // Detect OS
    $osRelease = file_exists('/etc/os-release') ? parse_ini_file('/etc/os-release') : [];
    $osId = $osRelease['ID'] ?? 'unknown';
    
    if (in_array($osId, ['ubuntu', 'debian'])) {
        echo "Installing packages for Ubuntu/Debian...\n";
        $packages = implode(' ', $missingPackages);
        system("apt update && apt install -y $packages");
    } elseif (in_array($osId, ['centos', 'rhel', 'rocky', 'almalinux'])) {
        echo "Installing packages for CentOS/RHEL/Rocky/Alma...\n";
        // Convert package names (php8.1-xxx -> php-xxx)
        $packages = array_map(function($pkg) {
            return str_replace('php8.1-', 'php-', $pkg);
        }, $missingPackages);
        $packages = implode(' ', $packages);
        system("dnf install -y $packages");
    } else {
        echo "Unknown OS: $osId\n";
        echo "Please install packages manually. See packages.txt\n";
    }
    
    echo "\n{$GREEN}✓ Installation complete!{$NC}\n";
    echo "\nRe-run this script to verify:\n";
    echo "  php cli/check-dependencies.php\n\n";
}

// ============================================
// Fix Composer Dependencies
// ============================================
if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    echo "═══════════════════════════════════════════════════════════\n";
    echo "  Install PHP Dependencies\n";
    echo "═══════════════════════════════════════════════════════════\n\n";
    
    if (exec("which composer 2>/dev/null", $output, $returnCode) && $returnCode === 0) {
        echo "Run this command to install PHP dependencies:\n\n";
        echo "  {$BLUE}composer install{$NC}\n\n";
    } else {
        echo "Composer is not installed. Install it first:\n\n";
        echo "  {$BLUE}curl -sS https://getcomposer.org/installer | php{$NC}\n";
        echo "  {$BLUE}sudo mv composer.phar /usr/local/bin/composer{$NC}\n";
        echo "  {$BLUE}sudo chmod +x /usr/local/bin/composer{$NC}\n\n";
    }
}

// ============================================
// Exit with appropriate code
// ============================================
if ($allGood) {
    exit(0);
} else {
    exit(1);
}
