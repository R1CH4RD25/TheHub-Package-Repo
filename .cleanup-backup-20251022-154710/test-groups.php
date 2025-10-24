<?php
/**
 * Test Google Groups API with Service Account
 * Usage: php test-groups.php email@woodsonisd.net
 */

require_once __DIR__ . '/src/bootstrap.php';

if ($argc < 2) {
    echo "Usage: php test-groups.php email@woodsonisd.net\n";
    exit(1);
}

$userEmail = $argv[1];
$groupEmail = $_ENV['STAFF_GROUP_EMAIL'] ?? null;
$serviceAccountPath = $_ENV['GOOGLE_SERVICE_ACCOUNT_JSON'] ?? null;
$adminEmail = $_ENV['GOOGLE_ADMIN_EMAIL'] ?? null;

echo "=== Google Groups API Test ===\n";
echo "User Email: {$userEmail}\n";
echo "Group Email: {$groupEmail}\n";
echo "Admin Email (impersonation): {$adminEmail}\n";
echo "Service Account: {$serviceAccountPath}\n";
echo "\n";

if (!$groupEmail || !$serviceAccountPath || !$adminEmail) {
    echo "❌ ERROR: Missing configuration in .env\n";
    exit(1);
}

if (!file_exists($serviceAccountPath)) {
    echo "❌ ERROR: Service account JSON not found\n";
    exit(1);
}

try {
    echo "Initializing Google Client...\n";
    $client = new \Google_Client();
    $client->setAuthConfig($serviceAccountPath);
    $client->setSubject($adminEmail);
    $client->addScope(\Google_Service_Directory::ADMIN_DIRECTORY_GROUP_READONLY);

    echo "Creating Directory service...\n";
    $service = new \Google_Service_Directory($client);

    echo "Checking if {$userEmail} is member of {$groupEmail}...\n";
    $member = $service->members->hasMember($groupEmail, $userEmail);
    $isMember = $member->getIsMember();

    echo "\n";
    if ($isMember) {
        echo "✅ SUCCESS: {$userEmail} IS a member of {$groupEmail}\n";
    } else {
        echo "❌ NOT A MEMBER: {$userEmail} is NOT in {$groupEmail}\n";
    }

} catch (\Google_Service_Exception $e) {
    echo "\n❌ Google API Error:\n";
    echo "Status: " . $e->getCode() . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "\nDetails:\n";
    print_r($e->getErrors());
    
    if ($e->getCode() == 403) {
        echo "\n⚠️  This usually means:\n";
        echo "1. Domain-Wide Delegation is not set up in Google Workspace Admin\n";
        echo "2. The service account doesn't have the required scope\n";
        echo "3. The admin email doesn't have permissions\n";
    }
    
} catch (\Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}
