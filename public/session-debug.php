<?php
// Debug session state
require_once __DIR__ . '/../src/bootstrap.php';

header('Content-Type: text/plain');

echo "=== SESSION DEBUG ===\n\n";

echo "Session Status: ";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "ACTIVE\n";
} else {
    echo "NOT ACTIVE\n";
    session_start();
}

echo "Session ID: " . session_id() . "\n";
echo "Session Name: " . session_name() . "\n";
echo "Session Save Path: " . session_save_path() . "\n\n";

echo "=== \$_SESSION Contents ===\n";
if (empty($_SESSION)) {
    echo "(empty)\n";
} else {
    print_r($_SESSION);
}

echo "\n=== \$_COOKIE Contents ===\n";
if (empty($_COOKIE)) {
    echo "(empty)\n";
} else {
    print_r($_COOKIE);
}

echo "\n=== Session Files ===\n";
$sessionPath = session_save_path();
if (is_dir($sessionPath)) {
    $files = scandir($sessionPath);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            echo "$file\n";
            $content = file_get_contents("$sessionPath/$file");
            echo "  Content: " . substr($content, 0, 200) . "...\n";
        }
    }
} else {
    echo "Session path doesn't exist or not accessible\n";
}
