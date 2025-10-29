<?php
/**
 * Comprehensive test of the Section Permission System
 */

require_once __DIR__ . '/src/bootstrap.php';

use Hub\SectionPermissions;
use Hub\NotificationService;

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║   SECTION PERMISSION SYSTEM - COMPREHENSIVE TEST             ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Test 1: Section Category
echo "┌─ TEST 1: Section Category ────────────────────────────────────┐\n";
try {
    $category = SectionPermissions::getSectionCategory('bullying-report');
    if ($category) {
        echo "✅ Category: {$category['name']}\n";
        echo "   Icon: {$category['icon']}\n";
        $reqs = json_decode($category['requirements'], true);
        if ($reqs) {
            echo "   Requirements: " . implode(', ', $reqs) . "\n";
        }
    } else {
        echo "⚠️  No category assigned\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 2: Submission Permissions
echo "┌─ TEST 2: Submission Permissions ──────────────────────────────┐\n";
$testUserId = 1; // Assuming user ID 1 exists (super_admin)
try {
    $canSubmit = SectionPermissions::canSubmit($testUserId, 'bullying-report', false);
    echo "✅ User $testUserId can submit: " . ($canSubmit ? 'YES' : 'NO') . "\n";
    
    $canSubmitAnon = SectionPermissions::canSubmit($testUserId, 'bullying-report', true);
    echo "✅ User $testUserId can submit anonymously: " . ($canSubmitAnon ? 'YES' : 'NO') . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 3: Review Permissions
echo "┌─ TEST 3: Review Permissions ──────────────────────────────────┐\n";
try {
    $canReview = SectionPermissions::canReview($testUserId, 'bullying-report');
    echo "✅ User $testUserId can review: " . ($canReview ? 'YES' : 'NO') . "\n";
    
    if ($canReview) {
        $perms = SectionPermissions::getReviewPermissions($testUserId, 'bullying-report');
        echo "   Permissions:\n";
        foreach ($perms as $perm => $value) {
            $icon = $value ? '✓' : '✗';
            echo "   $icon $perm\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 4: Guidelines
echo "┌─ TEST 4: Guidelines ──────────────────────────────────────────┐\n";
try {
    $submissionGuidelines = SectionPermissions::getGuidelines('bullying-report', 'submission');
    echo "✅ Submission Guidelines: " . count($submissionGuidelines) . " found\n";
    
    $reviewGuidelines = SectionPermissions::getGuidelines('bullying-report', 'review');
    echo "✅ Review Guidelines: " . count($reviewGuidelines) . " found\n";
    
    $generalGuidelines = SectionPermissions::getGuidelines('bullying-report', 'general');
    echo "✅ General Guidelines: " . count($generalGuidelines) . " found\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 5: Notification Recipients
echo "┌─ TEST 5: Notification Recipients ─────────────────────────────┐\n";
try {
    $recipients = SectionPermissions::getNotificationRecipients('bullying-report', 'submission');
    echo "✅ Notification Recipients: " . count($recipients) . " found\n";
    foreach ($recipients as $recipient) {
        $methods = [];
        if ($recipient['notify_email']) $methods[] = 'email';
        if ($recipient['notify_sms']) $methods[] = 'sms';
        echo "   - {$recipient['name']} ({$recipient['role_name']}): " . implode(', ', $methods) . "\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 6: Section Configuration
echo "┌─ TEST 6: Section Configuration ───────────────────────────────┐\n";
try {
    $config = SectionPermissions::getSectionConfig('bullying-report');
    echo "✅ Configuration loaded:\n";
    echo "   Status Tracking: " . ($config['enable_status_tracking'] ? 'YES' : 'NO') . "\n";
    echo "   Priority Levels: " . ($config['enable_priority_levels'] ? 'YES' : 'NO') . "\n";
    echo "   File Attachments: " . ($config['enable_file_attachments'] ? 'YES' : 'NO') . "\n";
    echo "   Notes/Comments: " . ($config['enable_notes_comments'] ? 'YES' : 'NO') . "\n";
    echo "   Assignment: " . ($config['enable_assignment'] ? 'YES' : 'NO') . "\n";
    if ($config['allowed_file_types']) {
        echo "   Allowed file types: " . implode(', ', $config['allowed_file_types']) . "\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 7: Validation
echo "┌─ TEST 7: Configuration Validation ────────────────────────────┐\n";
try {
    $db = Hub\Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id FROM sections WHERE slug = 'bullying-report'");
    $stmt->execute();
    $sectionId = $stmt->fetchColumn();
    
    $validation = SectionPermissions::validateSectionConfig($sectionId);
    echo "✅ Validation Status: " . ($validation['valid'] ? 'VALID' : 'INVALID') . "\n";
    
    if (!empty($validation['errors'])) {
        echo "   Errors:\n";
        foreach ($validation['errors'] as $error) {
            echo "   ❌ $error\n";
        }
    }
    
    if (!empty($validation['warnings'])) {
        echo "   Warnings:\n";
        foreach ($validation['warnings'] as $warning) {
            echo "   ⚠️  $warning\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║                    TEST COMPLETE                              ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
