<?php
/**
 * Auth Guard - Require authentication for admin pages
 */

require_once __DIR__ . '/../../core/Auth.php';

$config = require __DIR__ . '/../../config/config.php';
$auth = new Auth(__DIR__ . '/../../config/users.json');

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: /cms/admin/login.php');
    exit;
}

$currentUser = $auth->getCurrentUser();
