<?php
/**
 * Preview Page System
 *
 * Renders a page for preview purposes
 */

require_once __DIR__ . '/../core/PageManager.php';

$config = require __DIR__ . '/../config/config.php';
$reservedFolders = $config['reserved_folders'] ?? ['cms'];
$pageManager = new PageManager($config['root_dir'], $reservedFolders);

// Get page ID from query parameter
$pageId = $_GET['page_id'] ?? '';
$pagePath = $pageManager->getPagePath($pageId);

if (!$pagePath) {
    http_response_code(404);
    echo '<!doctype html><html><head><title>Page Not Found</title></head><body><h1>404 - Page Not Found</h1></body></html>';
    exit;
}

// Include and render the page
include $pagePath;
