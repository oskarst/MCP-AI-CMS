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
require_once __DIR__ . '/../core/BlogManager.php';

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
$blogManager = new BlogManager($config['root_dir'], $config['drafts_dir']);

// Helper function to normalize homepage page_id
function normalizePageId($pageId) {
    // Only accept "/" as alias for homepage (empty string)
    if ($pageId === '/') {
        return '';
    }

    return $pageId;
}

// Handler for find_and_replace_block_content tool
function handleFindAndReplaceBlockContent($input, $pageManager, $blockParser, $backupManager) {
    // Validate required parameters
    $pageId = isset($input['page_id']) ? normalizePageId($input['page_id']) : null;
    $blockName = $input['name'] ?? '';
    $search = $input['search'] ?? '';
    $replace = $input['replace'] ?? '';

    if ($pageId === null || !$blockName || $search === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required parameters: page_id, name, search']);
        exit;
    }

    // Optional parameters with defaults
    $mode = $input['mode'] ?? 'first';
    $caseSensitive = $input['case_sensitive'] ?? true;

    // Validate mode
    if (!in_array($mode, ['first', 'all'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid mode. Must be "first" or "all"']);
        exit;
    }

    // Get page path
    $pagePath = $pageManager->getPagePath($pageId);
    if (!$pagePath) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Page not found']);
        exit;
    }

    // Parse blocks
    $blocks = $blockParser->parseBlocks($pagePath);

    // Find the target block
    $targetBlock = null;
    $blockIndex = -1;
    foreach ($blocks as $index => $block) {
        if ($block['name'] === $blockName) {
            $targetBlock = $block;
            $blockIndex = $index;
            break;
        }
    }

    if (!$targetBlock) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Block not found']);
        exit;
    }

    // Perform find and replace
    $originalContent = $targetBlock['content'];
    $newContent = $originalContent;
    $replacements = 0;

    if ($mode === 'first') {
        // Replace only first occurrence
        if ($caseSensitive) {
            $pos = strpos($newContent, $search);
            if ($pos !== false) {
                $newContent = substr_replace($newContent, $replace, $pos, strlen($search));
                $replacements = 1;
            }
        } else {
            $pos = stripos($newContent, $search);
            if ($pos !== false) {
                // Get the actual match to preserve other case variations
                $actualMatch = substr($newContent, $pos, strlen($search));
                $newContent = substr_replace($newContent, $replace, $pos, strlen($actualMatch));
                $replacements = 1;
            }
        }
    } else {
        // Replace all occurrences
        if ($caseSensitive) {
            $newContent = str_replace($search, $replace, $originalContent, $count);
            $replacements = $count;
        } else {
            $newContent = str_ireplace($search, $replace, $originalContent, $count);
            $replacements = $count;
        }
    }

    // If no replacements, return without modifying file
    if ($replacements === 0) {
        echo json_encode([
            'success' => true,
            'replacements' => 0,
            'message' => 'No occurrences of the search text were found in this block.'
        ]);
        exit;
    }

    // Create backup before modifying
    $backupManager->createBackup($pageId, $pagePath);

    // Update the block
    $blockParser->updateBlock($pagePath, $blockName, $newContent, $targetBlock['custom'] ? true : null);

    // Return success with replacement count
    echo json_encode([
        'success' => true,
        'replacements' => $replacements
    ]);
    exit;
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

            // Return only metadata (name, role, custom) without content
            $blockMetadata = array_map(function($block) {
                return [
                    'name' => $block['name'],
                    'role' => $block['role'],
                    'custom' => $block['custom']
                ];
            }, $blocks);

            echo json_encode(['success' => true, 'blocks' => $blockMetadata]);
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
            $searchMode = $input['search_mode'] ?? 'case_insensitive';

            if (!$searchText) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing search_text parameter']);
                exit;
            }

            // Validate search mode
            $validModes = ['case_insensitive', 'case_sensitive', 'html_insensitive'];
            if (!in_array($searchMode, $validModes)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid search_mode. Must be: case_insensitive, case_sensitive, or html_insensitive']);
                exit;
            }

            // Search through all pages
            $pages = $pageManager->listPages();
            $matches = [];

            foreach ($pages as $page) {
                $blocks = $blockParser->parseBlocks($page['path']);

                foreach ($blocks as $block) {
                    $found = false;

                    // Apply search based on mode
                    switch ($searchMode) {
                        case 'case_sensitive':
                            // Case-sensitive search
                            $found = (strpos($block['content'], $searchText) !== false);
                            break;

                        case 'html_insensitive':
                            // Strip HTML tags from content and search text, then do case-insensitive search
                            $cleanContent = strip_tags($block['content']);
                            $cleanSearch = strip_tags($searchText);
                            $found = (stripos($cleanContent, $cleanSearch) !== false);
                            break;

                        case 'case_insensitive':
                        default:
                            // Case-insensitive search (default)
                            $found = (stripos($block['content'], $searchText) !== false);
                            break;
                    }

                    if ($found) {
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

        case 'create_page':
            $pageId = $input['page_id'] ?? '';
            $content = $input['content'] ?? '';

            if (!$pageId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing page_id parameter']);
                exit;
            }

            // Create the page
            $pageManager->createPage($pageId, $content);
            echo json_encode(['success' => true, 'page_id' => $pageId]);
            break;

        case 'read_page':
            $pageId = normalizePageId($input['page_id'] ?? '');

            if (!isset($input['page_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing page_id parameter']);
                exit;
            }

            $pagePath = $pageManager->getPagePath($pageId);
            if (!$pagePath) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Page not found']);
                exit;
            }

            // Read the full page content
            $content = file_get_contents($pagePath);
            echo json_encode([
                'success' => true,
                'page_id' => $pageId,
                'path' => $pagePath,
                'content' => $content
            ]);
            break;

        case 'read_block':
            $pageId = normalizePageId($input['page_id'] ?? '');
            $blockName = $input['name'] ?? '';

            if (!isset($input['page_id']) || !$blockName) {
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

            // Parse blocks and find the requested one
            $blocks = $blockParser->parseBlocks($pagePath);
            $foundBlock = null;

            foreach ($blocks as $block) {
                if ($block['name'] === $blockName) {
                    $foundBlock = $block;
                    break;
                }
            }

            if (!$foundBlock) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Block not found']);
                exit;
            }

            echo json_encode([
                'success' => true,
                'page_id' => $pageId,
                'block' => $foundBlock
            ]);
            break;

        case 'list_posts':
            $collectionId = $input['collection_id'] ?? 'blog';

            try {
                $posts = $blogManager->listPosts($collectionId);
                echo json_encode([
                    'success' => true,
                    'collection_id' => $collectionId,
                    'posts' => $posts,
                    'count' => count($posts)
                ]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        case 'create_post':
            $collectionId = $input['collection_id'] ?? 'blog';
            $slug = $input['slug'] ?? '';
            $content = $input['content'] ?? '';

            if (!$slug) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing slug parameter']);
                exit;
            }

            try {
                $postPath = $blogManager->createPost($collectionId, $slug, $content);
                echo json_encode([
                    'success' => true,
                    'collection_id' => $collectionId,
                    'slug' => $slug,
                    'path' => $postPath,
                    'status' => 'draft'
                ]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        case 'publish_post':
            $collectionId = $input['collection_id'] ?? 'blog';
            $slug = $input['slug'] ?? '';

            if (!$slug) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing slug parameter']);
                exit;
            }

            try {
                $blogManager->publishPost($collectionId, $slug);
                echo json_encode([
                    'success' => true,
                    'collection_id' => $collectionId,
                    'slug' => $slug,
                    'status' => 'published'
                ]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        case 'unpublish_post':
            $collectionId = $input['collection_id'] ?? 'blog';
            $slug = $input['slug'] ?? '';

            if (!$slug) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing slug parameter']);
                exit;
            }

            try {
                $blogManager->unpublishPost($collectionId, $slug);
                echo json_encode([
                    'success' => true,
                    'collection_id' => $collectionId,
                    'slug' => $slug,
                    'status' => 'draft'
                ]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        case 'find_and_replace_block_content':
            handleFindAndReplaceBlockContent($input, $pageManager, $blockParser, $backupManager);
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
