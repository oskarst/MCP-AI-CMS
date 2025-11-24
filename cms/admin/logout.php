<?php
/**
 * Admin Logout
 */

require_once __DIR__ . '/../core/Auth.php';

$auth = new Auth(__DIR__ . '/../config/users.json');
$auth->logout();

header('Location: /cms/admin/login.php');
exit;
