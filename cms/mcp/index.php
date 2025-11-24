<?php
/**
 * MCP HTTP Endpoint - AI-only API for ChatGPT, Claude, etc.
 *
 * Authentication: X-CMS-MCP-TOKEN header
 * Request format: POST /cms/mcp/index.php?tool=<tool_name>
 * Request body: JSON with tool arguments
 * Response: JSON with success/error fields
 */

// Load configuration and core classes
$config = require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/BlockParser.php';
require_once __DIR__ . '/../core/PageManager.php';
require_once __DIR__ . '/../core/BackupManager.php';

// Set JSON response header
header('Content-Type: application/json');

// Handle CORS if needed
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Verify authentication
$token = $_SERVER['HTTP_X_CMS_MCP_TOKEN'] ?? '';
if (!$token || $token !== $config['mcp_token']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized (invalid MCP token)']);
    exit;
}

// Verify POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get tool name from query parameter
$tool = $_GET['tool'] ?? '';
if (!$tool) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing tool parameter']);
    exit;
}

// Parse JSON request body
$input = json_decode(file_get_contents('php://input'), true);
if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON in request body']);
    exit;
}

// Initialize managers
$reservedFolders = $config['reserved_folders'] ?? ['cms'];
$pageManager = new PageManager($config['root_dir'], $reservedFolders);
$blockParser = new BlockParser();
$backupManager = new BackupManager($config['backups_dir'], $config['max_backups_per_page']);

// Helper function to normalize homepage page_id
function normalizePageId($pageId) {
    // Only accept "/" as alias for homepage (empty string)
    if ($pageId === '/') {
        return '';
    }

    return $pageId;
}

// Route to appropriate tool handler
try {
    switch ($tool) {
        case 'list_pages':
            $pages = $pageManager->listPages();
            echo json_encode(['success' => true, 'pages' => $pages]);
            break;

        case 'list_blocks':
            $pageId = normalizePageId($input['page_id'] ?? '');
            $pagePath = $pageManager->getPagePath($pageId);

            if (!$pagePath) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Page not found']);
                exit;
            }

            $blocks = $blockParser->parseBlocks($pagePath);
            echo json_encode(['success' => true, 'blocks' => $blocks]);
            break;

        case 'update_block':
            $pageId = isset($input['page_id']) ? normalizePageId($input['page_id']) : null;
            $blockName = $input['name'] ?? '';
            $content = $input['content'] ?? '';
            $custom = $input['custom'] ?? null;

            if ($pageId === null || !$blockName || $content === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
                exit;
            }

            $pagePath = $pageManager->getPagePath($pageId);
            if (!$pagePath) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Page not found']);
                exit;
            }

            // Create backup before updating
            $backupManager->createBackup($pageId, $pagePath);

            // Update the block
            $blockParser->updateBlock($pagePath, $blockName, $content, $custom);

            echo json_encode(['success' => true]);
            break;

        case 'duplicate_page':
            $sourcePageId = $input['source_page_id'] ?? '';
            $newPageId = $input['new_page_id'] ?? '';

            if (!$sourcePageId || !$newPageId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
                exit;
            }

            $pageManager->duplicatePage($sourcePageId, $newPageId);
            echo json_encode(['success' => true]);
            break;

        case 'delete_page':
            $pageId = normalizePageId($input['page_id'] ?? '');

            if ($pageId === null) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing page_id parameter']);
                exit;
            }

            $pagePath = $pageManager->getPagePath($pageId);
            if ($pagePath) {
                // Create backup before deleting
                $backupManager->createBackup($pageId, $pagePath);
            }

            $pageManager->deletePage($pageId);
            echo json_encode(['success' => true]);
            break;

        case 'list_backups':
            $pageId = normalizePageId($input['page_id'] ?? '');

            // Empty string is valid for homepage
            if (!isset($input['page_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing page_id parameter']);
                exit;
            }

            $backups = $backupManager->listBackups($pageId);
            echo json_encode(['success' => true, 'backups' => $backups]);
            break;

        case 'restore_backup':
            $pageId = normalizePageId($input['page_id'] ?? '');
            $timestamp = $input['timestamp'] ?? '';

            if (!isset($input['page_id']) || !$timestamp) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
                exit;
            }

            $pagePath = $pageManager->getPagePath($pageId);
            if (!$pagePath) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Page not found']);
                exit;
            }

            $backupManager->restoreBackup($pageId, $timestamp, $pagePath);
            echo json_encode(['success' => true]);
            break;

        case 'search_blocks':
            $searchText = $input['search_text'] ?? '';

            if (!$searchText) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing search_text parameter']);
                exit;
            }

            // Search through all pages
            $pages = $pageManager->listPages();
            $matches = [];

            foreach ($pages as $page) {
                $blocks = $blockParser->parseBlocks($page['path']);

                foreach ($blocks as $block) {
                    // Case-insensitive search
                    if (stripos($block['content'], $searchText) !== false) {
                        $matches[] = [
                            'page_id' => $page['id'],
                            'page_path' => $page['path'],
                            'block_name' => $block['name'],
                            'block_role' => $block['role'],
                            'block_custom' => $block['custom'],
                            'content_preview' => substr($block['content'], 0, 200) // First 200 chars
                        ];
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'matches' => $matches,
                'count' => count($matches),
                'disambiguation_required' => count($matches) > 1,
                'disambiguation_message' => count($matches) > 1
                    ? 'Multiple blocks contain the same text. Ask the user which page/section is correct.'
                    : null
            ]);
            break;

        case 'get_usage_tips':
            echo json_encode([
                'success' => true,
                'tips' => [
                    'Always use search_blocks before update_block',
                    'Save large responses to files: curl ... > file.json',
                    'Homepage page_id: use "" or "/"',
                    'Ask user when multiple matches found'
                ]
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown tool: ' . $tool]);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
