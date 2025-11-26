<?php
/**
 * Blog Post Preview System
 *
 * Renders a blog post for preview purposes (draft or published)
 */

require_once __DIR__ . '/../core/BlogManager.php';

$config = require __DIR__ . '/../config/config.php';
$blogManager = new BlogManager($config['root_dir'], $config['drafts_dir']);

// Get parameters
$collectionId = $_GET['collection'] ?? 'blog';
$slug = $_GET['slug'] ?? '';
$showDraft = isset($_GET['draft']) && $_GET['draft'] === '1';

if (empty($slug)) {
    http_response_code(404);
    echo '<!doctype html><html><head><title>Post Not Found</title></head><body><h1>404 - Post Not Found</h1><p>No slug provided.</p></body></html>';
    exit;
}

// Get post path
$status = $showDraft ? 'draft' : 'published';
$postPath = $blogManager->getPostPath($collectionId, $slug, $status);

if (!$postPath) {
    http_response_code(404);
    echo '<!doctype html><html><head><title>Post Not Found</title></head><body><h1>404 - Post Not Found</h1><p>The requested ' . htmlspecialchars($status) . ' post was not found.</p></body></html>';
    exit;
}

// Include and render the post
include $postPath;
