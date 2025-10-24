<?php

require_once __DIR__ . '/../src/bootstrap.php';

use Hub\PackageValidator;

// Read the package file
$packageJson = file_get_contents(__DIR__ . '/../packages/local/bullying-report_1.0.0.hubpkg');
$packageData = json_decode($packageJson, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die('JSON Error: ' . json_last_error_msg() . PHP_EOL);
}

// Validate the package
$validator = new PackageValidator();
$result = $validator->validate($packageData, 'new');

// Generate report
echo $validator->generateReport($result);

// Additional package details
echo PHP_EOL . "PACKAGE DETAILS" . PHP_EOL;
echo str_repeat('-', 80) . PHP_EOL;
echo "ID: " . $packageData['package']['id'] . PHP_EOL;
echo "Name: " . $packageData['package']['display_name'] . PHP_EOL;
echo "Version: " . $packageData['package']['version'] . PHP_EOL;
echo "Description: " . $packageData['package']['description'] . PHP_EOL;
echo "Fields: " . count($packageData['fields']) . PHP_EOL;
echo "Menu Items: " . count($packageData['menu_items']) . PHP_EOL;
echo "Permissions: " . implode(', ', array_keys(array_filter($packageData['permissions']))) . PHP_EOL;
echo PHP_EOL;

// Field summary
echo "FIELDS INCLUDED:" . PHP_EOL;
echo str_repeat('-', 80) . PHP_EOL;
foreach ($packageData['fields'] as $field) {
    $required = !empty($field['required']) ? '*' : '';
    echo sprintf("  %s. %s (%s)%s - %s\n", 
        $field['order'],
        $field['label'],
        $field['type'],
        $required,
        $field['help_text'] ?? ''
    );
}
echo PHP_EOL;

// Menu items
echo "MENU ITEMS:" . PHP_EOL;
echo str_repeat('-', 80) . PHP_EOL;
foreach ($packageData['menu_items'] as $item) {
    echo sprintf("  %s - %s (min role: %s)\n",
        $item['icon'],
        $item['label'],
        $item['minimum_role']
    );
}
