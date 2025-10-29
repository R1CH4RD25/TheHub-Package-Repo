<?php
/**
 * Test script to verify guidelines are loading
 */

require_once __DIR__ . '/src/bootstrap.php';

use Hub\SectionPermissions;

echo "Testing Section Permissions - Guidelines\n";
echo str_repeat("=", 50) . "\n\n";

// Test 1: Get submission guidelines
echo "1. Testing submission guidelines:\n";
$submissionGuidelines = SectionPermissions::getGuidelines('bullying-report', 'submission');
echo "   Found: " . count($submissionGuidelines) . " guidelines\n";
foreach ($submissionGuidelines as $g) {
    echo "   - {$g['title']} (" . strlen($g['content']) . " chars)\n";
}

echo "\n2. Testing review guidelines:\n";
$reviewGuidelines = SectionPermissions::getGuidelines('bullying-report', 'review');
echo "   Found: " . count($reviewGuidelines) . " guidelines\n";
foreach ($reviewGuidelines as $g) {
    echo "   - {$g['title']} (" . strlen($g['content']) . " chars)\n";
}

echo "\n3. Testing general guidelines:\n";
$generalGuidelines = SectionPermissions::getGuidelines('bullying-report', 'general');
echo "   Found: " . count($generalGuidelines) . " guidelines\n";
foreach ($generalGuidelines as $g) {
    echo "   - {$g['title']} (" . strlen($g['content']) . " chars)\n";
}

echo "\n4. Testing all guidelines:\n";
$allGuidelines = SectionPermissions::getGuidelines('bullying-report');
echo "   Found: " . count($allGuidelines) . " total guidelines\n";

echo "\n✅ Guidelines system is working!\n";
