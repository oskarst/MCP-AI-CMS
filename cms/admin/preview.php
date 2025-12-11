<?php
/**
 * Preview Page System
 *
 * Renders a page for preview purposes with auto-refresh via SSE
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

// SSE auto-refresh script (injected before </body>)
$sseScript = '';
if ($showDraft) {
    $escapedPageId = htmlspecialchars($pageId, ENT_QUOTES, 'UTF-8');
    $sseScript = <<<HTML
<script>
(function() {
    var eventSource = null;
    var reconnectDelay = 1000;

    function connect() {
        eventSource = new EventSource('/cms/admin/preview-sse.php?page_id={$escapedPageId}');

        eventSource.addEventListener('draft-changed', function(e) {
            console.log('Draft changed, reloading...');
            location.reload();
        });

        eventSource.addEventListener('draft-removed', function(e) {
            console.log('Draft removed');
            // Optionally redirect to live page or show message
        });

        eventSource.addEventListener('timeout', function(e) {
            eventSource.close();
            setTimeout(connect, reconnectDelay);
        });

        eventSource.onerror = function(e) {
            eventSource.close();
            setTimeout(connect, reconnectDelay);
        };
    }

    connect();
})();
</script>
HTML;
}

// If showing draft, create temporary file from draft content
if ($showDraft && $pageManager->hasDraft($pageId)) {
    $draftContent = $pageManager->getDraft($pageId);

    if ($draftContent === null) {
        http_response_code(404);
        echo '<!doctype html><html><head><title>Draft Not Found</title></head><body><h1>404 - Draft Not Found</h1></body></html>';
        exit;
    }

    // Inject SSE script before </body>
    if ($sseScript && stripos($draftContent, '</body>') !== false) {
        $draftContent = str_ireplace('</body>', $sseScript . '</body>', $draftContent);
    } else if ($sseScript) {
        $draftContent .= $sseScript;
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
