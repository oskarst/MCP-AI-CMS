<?php
/**
 * SSE endpoint for draft change notifications
 *
 * Watches draft file modification time and sends event when changed
 */

$config = require __DIR__ . '/../config/config.php';

// Get page ID from query
$pageId = $_GET['page_id'] ?? '';
if ($pageId === '') {
    $pageId = 'index';
}

// Determine draft file path
$draftPath = $config['drafts_dir'] . '/' . ($pageId ?: 'index') . '.draft.php';

// Set SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable nginx buffering

// Disable output buffering
if (ob_get_level()) {
    ob_end_clean();
}

// Track last modification time
$lastMtime = file_exists($draftPath) ? filemtime($draftPath) : 0;

// Send initial connection event
echo "event: connected\n";
echo "data: {\"status\":\"connected\",\"page_id\":\"" . addslashes($pageId) . "\"}\n\n";
flush();

// Timeout after 30 seconds (client will reconnect)
$startTime = time();
$timeout = 30;

while (true) {
    // Check if client disconnected
    if (connection_aborted()) {
        break;
    }

    // Check timeout
    if (time() - $startTime > $timeout) {
        echo "event: timeout\n";
        echo "data: {\"status\":\"timeout\"}\n\n";
        flush();
        break;
    }

    // Check for draft file changes
    clearstatcache(true, $draftPath);

    if (file_exists($draftPath)) {
        $currentMtime = filemtime($draftPath);

        if ($currentMtime > $lastMtime) {
            $lastMtime = $currentMtime;

            echo "event: draft-changed\n";
            echo "data: {\"status\":\"changed\",\"mtime\":{$currentMtime}}\n\n";
            flush();
        }
    } else if ($lastMtime > 0) {
        // Draft was deleted (published or discarded)
        $lastMtime = 0;

        echo "event: draft-removed\n";
        echo "data: {\"status\":\"removed\"}\n\n";
        flush();
    }

    // Send keepalive every 15 seconds
    if ((time() - $startTime) % 15 === 0) {
        echo ": keepalive\n\n";
        flush();
    }

    // Sleep 1 second between checks
    sleep(1);
}
