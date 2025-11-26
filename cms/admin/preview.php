<?php
/**
 * Preview Page System
 *
 * Renders a page for preview purposes
 */

require_once __DIR__ . '/../core/PageManager.php';
require_once __DIR__ . '/../core/PageSettings.php';

$config = require __DIR__ . '/../config/config.php';
$reservedFolders = $config['reserved_folders'] ?? ['cms'];
$pageSettings = new PageSettings($config['cms_dir'] . '/settings');
$pageManager = new PageManager($config['root_dir'], $reservedFolders, $config['drafts_dir'] ?? null, null, null, $pageSettings);

// Get page ID and draft parameter
$pageId = $_GET['page_id'] ?? '';
$showDraft = isset($_GET['draft']) && $_GET['draft'] === '1';

// If showing draft, create temporary file from draft content
if ($showDraft && $pageManager->hasDraft($pageId)) {
    $draftContent = $pageManager->getDraft($pageId);

    if ($draftContent === null) {
        http_response_code(404);
        echo '<!doctype html><html><head><title>Draft Not Found</title></head><body><h1>404 - Draft Not Found</h1></body></html>';
        exit;
    }

    // Create a temporary file with the draft content
    $tempFile = tempnam(sys_get_temp_dir(), 'cms_preview_');
    file_put_contents($tempFile, $draftContent);

    // Include and render the draft
    include $tempFile;

    // Clean up
    @unlink($tempFile);
} else {
    // Show live page
    $pagePath = $pageManager->getPagePath($pageId);

    if (!$pagePath) {
        http_response_code(404);
        echo '<!doctype html><html><head><title>Page Not Found</title></head><body><h1>404 - Page Not Found</h1></body></html>';
        exit;
    }

    // Include and render the page
    include $pagePath;
}
