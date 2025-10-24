<?php

require_once __DIR__ . '/../src/bootstrap.php';

use Hub\Email;

try {
    $email = new Email();
    $result = $email->sendTestEmail('richard.sullivan@woodsonisd.net');
    
    echo "✓ Test email sent successfully to richard.sullivan@woodsonisd.net\n";
    echo "Check your inbox (and spam folder if needed).\n";
    
} catch (Exception $e) {
    echo "✗ Failed to send test email\n";
    echo "Error: " . $e->getMessage() . "\n";
}
