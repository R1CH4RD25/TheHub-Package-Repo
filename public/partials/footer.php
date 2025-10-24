<?php
use Hub\Layout;

// Determine page type based on current script
$isAdminPage = (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false);

if ($isAdminPage) {
    Layout::renderFooter($user, 'dashboard');
} else {
    Layout::renderFooter($user, 'hub');
}
