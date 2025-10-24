<?php
/**
 * Maintenance Fuel & Travel Section - Redirect to existing fuel-entry
 * 
 * This keeps the existing fuel entry system working while we transition
 * to the new modular structure.
 */

require_once __DIR__ . '/../../../src/bootstrap.php';

use Hub\Auth;

// Require section access
Auth::requireSectionAccess('fuel-travel', 'staff');

// Redirect to the existing fuel entry page
header('Location: /fuel-entry.php');
exit;
