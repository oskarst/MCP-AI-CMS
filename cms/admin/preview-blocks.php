<?php
/**
 * Preview Page with Block Visualization - Iframe Approach
 *
 * Shows the page in an iframe with block labels overlay
 */

require_once __DIR__ . '/../core/PageManager.php';
require_once __DIR__ . '/../core/BlockParser.php';
require_once __DIR__ . '/../core/PageSettings.php';

$config = require __DIR__ . '/../config/config.php';
$reservedFolders = $config['reserved_folders'] ?? ['cms'];
$pageSettings = new PageSettings($config['cms_dir'] . '/settings');
$pageManager = new PageManager($config['root_dir'], $reservedFolders, null, null, null, $pageSettings);
$blockParser = new BlockParser();

// Get page ID from query parameter
$pageId = $_GET['page_id'] ?? '';

// Check if this is the iframe content request
if (isset($_GET['iframe']) && $_GET['iframe'] === '1') {
    // Output the page with labels and borders injected directly
    $pagePath = $pageManager->getPagePath($pageId);
    if ($pagePath) {
        $blocks = $blockParser->parseBlocks($pagePath);

        // Capture page HTML
        ob_start();
        include $pagePath;
        $pageHtml = ob_get_clean();

        // Inject CSS for block labels
        $labelStyles = '
        <style>
        .cms-preview-label {
            display: inline-block !important;
            position: absolute !important;
            top: -18px !important;
            left: 10px !important;
            background: #9333ea !important;
            color: white !important;
            padding: 4px 10px !important;
            border-radius: 4px !important;
            font-family: Monaco, Courier, monospace !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            z-index: 999999 !important;
            line-height: 1.2 !important;
            box-shadow: 0 2px 6px rgba(147, 51, 234, 0.5) !important;
            pointer-events: none !important;
        }
        .cms-preview-wrapper {
            outline: 3px solid #9333ea !important;
            position: relative !important;
            margin: 25px 0 !important;
        }
        </style>
        ';
        $pageHtml = str_replace('</head>', $labelStyles . '</head>', $pageHtml);

        // Inject labels directly into HTML for each block
        $labelCount = 0;
        foreach ($blocks as $block) {
            $blockName = $block['name'];
            $blockContent = trim($block['content']);

            // Get first line of content (without HTML tags) to search for
            $lines = explode("\n", strip_tags($blockContent));
            $searchSnippet = '';
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && strlen($line) > 10) {
                    $searchSnippet = substr($line, 0, 60);
                    break;
                }
            }

            if (!empty($searchSnippet)) {
                $pos = strpos($pageHtml, $searchSnippet);
                if ($pos !== false) {
                    // Find the HTML tag that contains this text
                    $before = substr($pageHtml, 0, $pos);

                    // Find the last opening tag before this position
                    preg_match_all('/<([a-z][a-z0-9]*)[^>]*>/i', $before, $matches, PREG_OFFSET_CAPTURE);

                    if (!empty($matches[0])) {
                        $lastTag = end($matches[0]);
                        $tagPos = $lastTag[1];
                        $tagHtml = $lastTag[0];

                        // Add wrapper class and label
                        $label = '<span class="cms-preview-label">' . htmlspecialchars($blockName) . '</span>';

                        // Add class to the tag
                        if (strpos($tagHtml, 'class=') !== false) {
                            $newTag = preg_replace('/class=["\']([^"\']*)["\']/', 'class="$1 cms-preview-wrapper"', $tagHtml);
                        } else {
                            $newTag = str_replace('>', ' class="cms-preview-wrapper">', $tagHtml);
                        }

                        $pageHtml = substr_replace($pageHtml, $newTag . $label, $tagPos, strlen($tagHtml));
                        $labelCount++;
                    }
                }
            }
        }

        echo $pageHtml;
    }
    exit;
}

// Main preview page
$pagePath = $pageManager->getPagePath($pageId);

if (!$pagePath) {
    http_response_code(404);
    echo '<!doctype html><html><head><title>Page Not Found</title></head><body><h1>404 - Page Not Found</h1></body></html>';
    exit;
}

// Parse blocks to get their names
$blocks = $blockParser->parseBlocks($pagePath);
$blocksJson = json_encode($blocks);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Block Preview - <?php echo htmlspecialchars($pageId ?: 'Homepage'); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            overflow: hidden;
        }

        #preview-banner {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            z-index: 9999999;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        #preview-banner strong {
            font-weight: 600;
        }

        #preview-banner a {
            color: white;
            text-decoration: underline;
        }

        #preview-iframe {
            position: fixed;
            top: 60px;
            left: 0;
            width: 100%;
            height: calc(100vh - 60px);
            border: none;
        }
    </style>
</head>
<body>
    <div id="preview-banner">
        <strong>🎨 Block Preview</strong>
        <span>Page: <strong><?php echo htmlspecialchars($pageId ?: 'Homepage'); ?></strong></span>
        <span>Blocks: <strong><?php echo count($blocks); ?></strong></span>
        <span id="labels-count">Labels: <strong>0</strong></span>
        <a href="/cms/admin/pages.php">← Back</a>
        <a href="/cms/admin/edit.php?page_id=<?php echo urlencode($pageId); ?>">Edit</a>
    </div>

    <iframe id="preview-iframe" src="?page_id=<?php echo urlencode($pageId); ?>&iframe=1"></iframe>

    <script>
        // Simple iframe load handler
        const iframe = document.getElementById('preview-iframe');

        iframe.addEventListener('load', function() {
            try {
                const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                const labels = iframeDoc.querySelectorAll('.cms-preview-label');
                document.getElementById('labels-count').innerHTML = 'Labels: <strong>' + labels.length + '</strong>';
            } catch (e) {
                // Cross-origin or other error - just show blocks count
                console.log('Could not access iframe content');
            }
        });
    </script>
</body>
</html>
