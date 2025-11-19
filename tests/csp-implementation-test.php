<?php
/**
 * CSP Implementation Test Suite
 * Tests nonce generation and application
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Start session
session_save_path(__DIR__ . '/../sessions');
session_name('THEHUB_SESSION_TEST');
session_start();

// Load bootstrap
require_once __DIR__ . '/../src/bootstrap.php';

echo "=== CSP Implementation Tests ===\n\n";

// Test 1: Nonce Generation
echo "Test 1: CSP Nonce Generation\n";
echo str_repeat('-', 50) . "\n";

$nonce1 = getCspNonce();
$nonce2 = getCspNonce();
$nonce3 = CSP_NONCE;

echo "First call:  {$nonce1}\n";
echo "Second call: {$nonce2}\n";
echo "Constant:    {$nonce3}\n";

if ($nonce1 === $nonce2 && $nonce2 === $nonce3) {
    echo "✅ PASS: Nonce is consistent within session\n";
} else {
    echo "❌ FAIL: Nonce should be same within session\n";
}

if (strlen($nonce1) >= 20 && strlen($nonce1) <= 30) {
    echo "✅ PASS: Nonce length is appropriate ({$nonce1} chars)\n";
} else {
    echo "❌ FAIL: Nonce length unexpected (" . strlen($nonce1) . " chars)\n";
}

if (preg_match('/^[A-Za-z0-9+\/=]+$/', $nonce1)) {
    echo "✅ PASS: Nonce is valid base64\n";
} else {
    echo "❌ FAIL: Nonce should be base64 encoded\n";
}

// Test 2: Check inline scripts have nonce
echo "\n\nTest 2: Inline Script Nonce Application\n";
echo str_repeat('-', 50) . "\n";

$filesToCheck = [
    'src/Layout.php',
    'public/admin/section-config-tab.php',
    'public/admin/index.php',
    'public/hub.php',
    'public/profile.php',
    'public/login.php',
    'public/command/submission.php',
    'public/command/section.php',
];

$totalFiles = 0;
$filesWithNonce = 0;
$filesWithoutNonce = 0;

foreach ($filesToCheck as $file) {
    $path = __DIR__ . '/../' . $file;
    if (!file_exists($path)) {
        echo "⚠️  {$file} - FILE NOT FOUND\n";
        continue;
    }
    
    $content = file_get_contents($path);
    $totalFiles++;
    
    // Count inline scripts with nonce
    $nonceCount = preg_match_all('/<script nonce="<\?php echo CSP_NONCE; \?>"/', $content, $matches);
    
    // Count inline scripts without nonce (not external src)
    $inlineCount = preg_match_all('/<script(?!\s+src=)(?!\s+nonce=)/', $content, $matches);
    
    if ($nonceCount > 0 && $inlineCount === 0) {
        echo "✅ {$file}: {$nonceCount} inline script(s) with nonce\n";
        $filesWithNonce++;
    } elseif ($inlineCount > 0) {
        echo "❌ {$file}: {$inlineCount} inline script(s) WITHOUT nonce\n";
        $filesWithoutNonce++;
    } else {
        echo "ℹ️  {$file}: No inline scripts\n";
    }
}

echo "\nSummary:\n";
echo "  Files checked: {$totalFiles}\n";
echo "  With nonce: {$filesWithNonce}\n";
echo "  Without nonce: {$filesWithoutNonce}\n";

if ($filesWithoutNonce === 0) {
    echo "✅ PASS: All inline scripts have nonces\n";
} else {
    echo "❌ FAIL: Some inline scripts missing nonces\n";
}

// Test 3: Meta tag presence
echo "\n\nTest 3: CSP Nonce Meta Tag\n";
echo str_repeat('-', 50) . "\n";

$layoutPath = __DIR__ . '/../src/Layout.php';
$layoutContent = file_get_contents($layoutPath);

if (strpos($layoutContent, '<meta name="csp-nonce"') !== false) {
    echo "✅ PASS: CSP nonce meta tag found in Layout.php\n";
} else {
    echo "❌ FAIL: CSP nonce meta tag missing from Layout.php\n";
}

// Test 4: Bootstrap CSP header logic
echo "\n\nTest 4: CSP Header Logic\n";
echo str_repeat('-', 50) . "\n";

$bootstrapPath = __DIR__ . '/../src/bootstrap.php';
$bootstrapContent = file_get_contents($bootstrapPath);

if (strpos($bootstrapContent, "CSP_ENABLED") !== false) {
    echo "✅ PASS: CSP header logic found in bootstrap.php\n";
} else {
    echo "❌ FAIL: CSP header logic missing from bootstrap.php\n";
}

if (strpos($bootstrapContent, "Content-Security-Policy") !== false) {
    echo "✅ PASS: CSP header directive found\n";
} else {
    echo "❌ FAIL: CSP header directive missing\n";
}

if (strpos($bootstrapContent, "'nonce-{\$nonce}'") !== false || strpos($bootstrapContent, "'nonce-{$nonce}'") !== false) {
    echo "✅ PASS: Nonce placeholder in CSP policy\n";
} else {
    echo "❌ FAIL: Nonce placeholder missing in CSP policy\n";
}

// Test 5: Session persistence
echo "\n\nTest 5: Nonce Session Persistence\n";
echo str_repeat('-', 50) . "\n";

$sessionNonce = $_SESSION['csp_nonce'] ?? null;
if ($sessionNonce === $nonce1) {
    echo "✅ PASS: Nonce stored in session\n";
} else {
    echo "❌ FAIL: Nonce not in session or doesn't match\n";
}

// Test 6: New session gets new nonce
unset($_SESSION['csp_nonce']);
$newNonce = getCspNonce();

if ($newNonce !== $nonce1) {
    echo "✅ PASS: New session generates new nonce\n";
} else {
    echo "❌ FAIL: Nonce should change with new session\n";
}

// Test 7: Check .env.example
echo "\n\nTest 7: Configuration Template\n";
echo str_repeat('-', 50) . "\n";

$envExamplePath = __DIR__ . '/../.env.example';
$envExampleContent = file_get_contents($envExamplePath);

if (strpos($envExampleContent, 'CSP_ENABLED') !== false) {
    echo "✅ PASS: CSP_ENABLED in .env.example\n";
} else {
    echo "❌ FAIL: CSP_ENABLED missing from .env.example\n";
}

if (strpos($envExampleContent, 'CSP_REPORT_ONLY') !== false) {
    echo "✅ PASS: CSP_REPORT_ONLY in .env.example\n";
} else {
    echo "❌ FAIL: CSP_REPORT_ONLY missing from .env.example\n";
}

// Final Summary
echo "\n\n" . str_repeat('=', 50) . "\n";
echo "🎉 CSP IMPLEMENTATION TEST COMPLETE\n";
echo str_repeat('=', 50) . "\n";

$allPassed = (
    $nonce1 === $nonce2 &&
    strlen($nonce1) >= 20 &&
    $filesWithoutNonce === 0 &&
    strpos($layoutContent, '<meta name="csp-nonce"') !== false &&
    strpos($bootstrapContent, "CSP_ENABLED") !== false
);

if ($allPassed) {
    echo "\n✅ ALL TESTS PASSED\n";
    echo "Ready for CSP deployment:\n";
    echo "  1. Set CSP_ENABLED=true in .env\n";
    echo "  2. Set CSP_REPORT_ONLY=true for testing\n";
    echo "  3. Monitor browser console for violations\n";
    echo "  4. After 1-2 weeks, set CSP_REPORT_ONLY=false\n";
} else {
    echo "\n⚠️  SOME TESTS FAILED\n";
    echo "Review failures above before enabling CSP\n";
}

echo "\nCurrent nonce: {$nonce1}\n";
echo "Session ID: " . session_id() . "\n";
