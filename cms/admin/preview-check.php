<?php
/**
 * Draft modification time check endpoint
 * Returns JSON with draft mtime for polling
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$config = require __DIR__ . '/../config/config.php';

$pageId = $_GET['page_id'] ?? '';
if ($pageId === '') {
    $pageId = 'index';
}

$draftPath = $config['drafts_dir'] . '/' . ($pageId ?: 'index') . '.draft.php';

if (file_exists($draftPath)) {
    echo json_encode([
        'exists' => true,
        'mtime' => filemtime($draftPath)
    ]);
} else {
    echo json_encode([
        'exists' => false,
        'mtime' => 0
    ]);
}
