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
require_once __DIR__ . '/../core/UploadManager.php';

// Set JSON response header
header('Content-Type: application/json');

// Handle CORS if needed
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// IP Whitelisting check
if (!empty($config['mcp_ip_whitelist'])) {
    $allowedIps = array_map('trim', explode(',', $config['mcp_ip_whitelist']));
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';

    if (!in_array($clientIp, $allowedIps)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied - IP not whitelisted']);
        exit;
    }
}

// Rate limiting check
if ($config['mcp_rate_limit_enabled'] ?? false) {
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimitFile = __DIR__ . '/../logs/rate_limit_' . md5($clientIp) . '.json';
    $now = time();
    $window = $config['mcp_rate_limit_window'] ?? 60;
    $maxRequests = $config['mcp_rate_limit_requests'] ?? 60;

    // Load existing request log
    $requests = [];
    if (file_exists($rateLimitFile)) {
        $data = json_decode(file_get_contents($rateLimitFile), true);
        $requests = $data['requests'] ?? [];
    }

    // Remove old requests outside the time window
    $requests = array_filter($requests, function($timestamp) use ($now, $window) {
        return ($now - $timestamp) < $window;
    });

    // Check if limit exceeded
    if (count($requests) >= $maxRequests) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Rate limit exceeded',
            'retry_after' => $window - ($now - min($requests))
        ]);
        exit;
    }

    // Add current request
    $requests[] = $now;

    // Save updated request log
    file_put_contents($rateLimitFile, json_encode(['requests' => $requests]));
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
$pageManager = new PageManager($config['root_dir'], $reservedFolders, $config['drafts_dir'] ?? null);
$blockParser = new BlockParser();
$backupManager = new BackupManager($config['backups_dir'], $config['max_backups_per_page']);
$blogManager = new BlogManager($config['root_dir'], $config['drafts_dir']);
$uploadManager = new UploadManager(
    $config['root_dir'],
    $config['uploads_dir'] ?? 'assets/content/',
    $config['image_thumbnail_width'] ?? 300,
    $config['image_thumbnail_height'] ?? 300,
    $config['image_full_width'] ?? 1920,
    $config['image_full_height'] ?? 1080
);

// Helper function to normalize homepage page_id
function normalizePageId($pageId) {
    // Only accept "/" as alias for homepage (empty string)
    if ($pageId === '/') {
        return '';
    }

    return $pageId;
}

// Handler for insert_block tool
function handleInsertBlock($input, $pageManager, $blockParser, $backupManager) {
    // Validate required parameters
    $pageId = isset($input['page_id']) ? normalizePageId($input['page_id']) : null;
    $position = $input['position'] ?? null;
    $name = $input['name'] ?? '';
    $content = $input['content'] ?? '';

    if ($pageId === null || !$position || !$name || $content === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required parameters: page_id, position, name, content']);
        exit;
    }

    // Validate position structure
    if (!is_array($position) || !isset($position['type'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid position parameter']);
        exit;
    }

    $positionType = $position['type'];
    $referenceBlockName = $position['block_name'] ?? null;

    // Validate position type
    $validTypes = ['before_block', 'after_block', 'at_end'];
    if (!in_array($positionType, $validTypes)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid position.type; expected before_block, after_block, or at_end']);
        exit;
    }

    // Validate reference block name for before_block/after_block
    if (in_array($positionType, ['before_block', 'after_block']) && !$referenceBlockName) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'position.block_name is required for before_block/after_block']);
        exit;
    }

    // Optional parameters
    $role = $input['role'] ?? null;
    $custom = $input['custom'] ?? false;

    // Get page path
    $pagePath = $pageManager->getPagePath($pageId);
    if (!$pagePath) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Page not found']);
        exit;
    }

    // Get current content (draft if exists, otherwise live page)
    $fileContent = $pageManager->hasDraft($pageId)
        ? $pageManager->getDraft($pageId)
        : file_get_contents($pagePath);

    if ($fileContent === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to read page file']);
        exit;
    }

    // Create a temporary file to parse blocks
    $tempFile = tempnam(sys_get_temp_dir(), 'cms_draft_');
    file_put_contents($tempFile, $fileContent);

    // Parse existing blocks
    $blocks = $blockParser->parseBlocks($tempFile);

    // Check for duplicate block name
    foreach ($blocks as $block) {
        if ($block['name'] === $name) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Block with name '{$name}' already exists on this page"]);
            exit;
        }
    }

    // Determine insertion position
    $insertPos = null;

    switch ($positionType) {
        case 'before_block':
            // Find the reference block and insert before it
            $found = false;
            foreach ($blocks as $block) {
                if ($block['name'] === $referenceBlockName) {
                    $insertPos = $block['start_pos'];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => "Reference block '{$referenceBlockName}' not found on this page"]);
                exit;
            }
            break;

        case 'after_block':
            // Find the reference block and insert after it
            $found = false;
            foreach ($blocks as $block) {
                if ($block['name'] === $referenceBlockName) {
                    $insertPos = $block['end_pos'];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => "Reference block '{$referenceBlockName}' not found on this page"]);
                exit;
            }
            break;

        case 'at_end':
            // Insert before </body> tag if found, otherwise at end of file
            $bodyClosePos = stripos($fileContent, '</body>');
            if ($bodyClosePos !== false) {
                $insertPos = $bodyClosePos;
            } else {
                $insertPos = strlen($fileContent);
            }
            break;
    }

    // Build the new block markup
    $attributes = ['name' => $name];
    if ($role) {
        $attributes['role'] = $role;
    }
    if ($custom) {
        $attributes['custom'] = '1';
    }

    $attrString = '';
    foreach ($attributes as $key => $value) {
        $attrString .= "{$key}={$value} ";
    }
    $attrString = rtrim($attrString);

    // Use PHP comment style (consistent with BlockParser)
    $newBlockMarkup = "\n<?php /* CMS:BLOCK {$attrString} start */ ?>\n";
    $newBlockMarkup .= $content;
    $newBlockMarkup .= "\n<?php /* CMS:BLOCK name={$name} end */ ?>\n";

    // Insert the new block
    $newFileContent = substr($fileContent, 0, $insertPos) . $newBlockMarkup . substr($fileContent, $insertPos);

    try {
        // Save as draft
        $pageManager->saveDraft($pageId, $newFileContent);

        // Create backup of live page (not draft)
        $backupManager->createBackup($pageId, $pagePath);

        echo json_encode(['success' => true, 'message' => 'Block inserted and saved as draft']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save draft: ' . $e->getMessage()]);
    } finally {
        // Clean up temporary file
        @unlink($tempFile);
    }
    exit;
}

// Handler for search_in_page tool
function handleSearchInPage($input, $pageManager) {
    // Validate required parameters
    $pageId = isset($input['page_id']) ? normalizePageId($input['page_id']) : null;
    $search = $input['search'] ?? '';

    if ($pageId === null || $search === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required parameters: page_id, search']);
        exit;
    }

    // Optional parameters
    $limit = $input['limit'] ?? 20;
    $caseSensitive = $input['case_sensitive'] ?? false;

    // Get page path
    $pagePath = $pageManager->getPagePath($pageId);
    if (!$pagePath || !is_readable($pagePath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Page not found']);
        exit;
    }

    // Read file content
    $content = file_get_contents($pagePath);
    if ($content === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to read page file']);
        exit;
    }

    // Split into lines
    $lines = preg_split("/\r\n|\n|\r/", $content);
    $matches = [];
    $matchCount = 0;

    // Search through lines
    for ($i = 0; $i < count($lines) && $matchCount < $limit; $i++) {
        $line = $lines[$i];
        $found = false;

        if ($caseSensitive) {
            $found = (strpos($line, $search) !== false);
        } else {
            $found = (stripos($line, $search) !== false);
        }

        if ($found) {
            // Determine snippet window (current line + up to 5 lines after)
            $startLine = $i + 1; // 1-based
            $endLine = min($i + 6, count($lines)); // up to 5 lines after

            // Build snippet
            $snippetLines = array_slice($lines, $i, $endLine - $startLine + 1);
            $snippet = implode("\n", $snippetLines);

            // Trim snippet to ~250 chars
            if (strlen($snippet) > 250) {
                $snippet = substr($snippet, 0, 250) . '...';
            }

            $matches[] = [
                'start_line' => $startLine,
                'end_line' => $endLine,
                'snippet' => $snippet
            ];

            $matchCount++;
        }
    }

    echo json_encode([
        'success' => true,
        'matches' => $matches
    ]);
    exit;
}

// Handler for get_page_region tool
function handleGetPageRegion($input, $pageManager) {
    // Validate required parameters
    $pageId = isset($input['page_id']) ? normalizePageId($input['page_id']) : null;
    $startLine = $input['start_line'] ?? null;
    $endLine = $input['end_line'] ?? null;

    if ($pageId === null || $startLine === null || $endLine === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required parameters: page_id, start_line, end_line']);
        exit;
    }

    // Validate line numbers
    if (!is_int($startLine) || !is_int($endLine) || $startLine < 1 || $endLine < $startLine) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid line range']);
        exit;
    }

    // Optional parameters
    $maxChars = $input['max_chars'] ?? 4000;

    // Get page path
    $pagePath = $pageManager->getPagePath($pageId);
    if (!$pagePath || !is_readable($pagePath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Page not found']);
        exit;
    }

    // Read file content
    $content = file_get_contents($pagePath);
    if ($content === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to read page file']);
        exit;
    }

    // Split into lines
    $lines = preg_split("/\r\n|\n|\r/", $content);
    $totalLines = count($lines);

    // Validate start line
    if ($startLine > $totalLines) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid line range']);
        exit;
    }

    // Clamp end line
    $actualEndLine = min($endLine, $totalLines);

    // Extract region, respecting max_chars
    $regionLines = [];
    $charCount = 0;
    $startIdx = $startLine - 1; // Convert to 0-based

    for ($i = $startIdx; $i < $actualEndLine; $i++) {
        $lineContent = $lines[$i];
        $lineLength = strlen($lineContent) + 1; // +1 for newline

        if ($charCount + $lineLength > $maxChars && $charCount > 0) {
            // Stop if we exceed max_chars
            $actualEndLine = $i; // Adjust actual end line
            break;
        }

        $regionLines[] = $lineContent;
        $charCount += $lineLength;
    }

    $region = implode("\n", $regionLines);

    echo json_encode([
        'success' => true,
        'region' => $region,
        'start_line' => $startLine,
        'end_line' => $actualEndLine
    ]);
    exit;
}

// Handler for update_page_region tool
function handleUpdatePageRegion($input, $pageManager, $backupManager) {
    // Validate required parameters
    $pageId = isset($input['page_id']) ? normalizePageId($input['page_id']) : null;
    $startLine = $input['start_line'] ?? null;
    $endLine = $input['end_line'] ?? null;
    $oldRegion = $input['old_region'] ?? '';
    $newRegion = $input['new_region'] ?? '';

    if ($pageId === null || $startLine === null || $endLine === null || $oldRegion === '' || $newRegion === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required parameters: page_id, start_line, end_line, old_region, new_region']);
        exit;
    }

    // Validate line numbers
    if (!is_int($startLine) || !is_int($endLine) || $startLine < 1 || $endLine < $startLine) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid line range']);
        exit;
    }

    // Get page path
    $pagePath = $pageManager->getPagePath($pageId);
    if (!$pagePath) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Page not found']);
        exit;
    }

    // Get current content (draft if exists, otherwise live page)
    $content = $pageManager->hasDraft($pageId)
        ? $pageManager->getDraft($pageId)
        : file_get_contents($pagePath);

    if ($content === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to read page file']);
        exit;
    }

    // Split into lines
    $lines = preg_split("/\r\n|\n|\r/", $content);
    $totalLines = count($lines);

    // Validate line range
    if ($startLine > $totalLines || $endLine > $totalLines) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid line range']);
        exit;
    }

    // Extract current region
    $startIdx = $startLine - 1; // Convert to 0-based
    $count = $endLine - $startLine + 1;
    $currentRegionLines = array_slice($lines, $startIdx, $count);
    $currentRegion = implode("\n", $currentRegionLines);

    // Check if old_region matches current region (optimistic locking)
    if ($currentRegion !== $oldRegion) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'Region has changed since retrieval'
        ]);
        exit;
    }

    // Split new region into lines
    $newRegionLines = preg_split("/\r\n|\n|\r/", $newRegion);

    // Replace the region
    array_splice($lines, $startIdx, $count, $newRegionLines);

    // Join back into file content
    $newContent = implode("\n", $lines);

    // Save as draft
    try {
        $pageManager->saveDraft($pageId, $newContent);

        // Create backup of live page (not draft)
        $backupManager->createBackup($pageId, $pagePath);

        echo json_encode(['success' => true, 'message' => 'Page region updated and saved as draft']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save draft: ' . $e->getMessage()]);
    }
    exit;
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

    // Get current content (draft if exists, otherwise live page)
    $currentContent = $pageManager->hasDraft($pageId)
        ? $pageManager->getDraft($pageId)
        : file_get_contents($pagePath);

    // Create a temporary file to parse blocks
    $tempFile = tempnam(sys_get_temp_dir(), 'cms_draft_');
    file_put_contents($tempFile, $currentContent);

    // Parse blocks from the working content
    $blocks = $blockParser->parseBlocks($tempFile);

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
        @unlink($tempFile);
        echo json_encode([
            'success' => true,
            'replacements' => 0,
            'message' => 'No occurrences of the search text were found in this block.'
        ]);
        exit;
    }

    try {
        // Update the block in the temporary file
        $blockParser->updateBlock($tempFile, $blockName, $newContent, $targetBlock['custom'] ? true : null);

        // Read the updated content
        $updatedContent = file_get_contents($tempFile);

        // Save as draft
        $pageManager->saveDraft($pageId, $updatedContent);

        // Create backup of live page (not draft)
        $backupManager->createBackup($pageId, $pagePath);

        // Return success with replacement count
        echo json_encode([
            'success' => true,
            'replacements' => $replacements,
            'message' => 'Content replaced and saved as draft'
        ]);
    } finally {
        // Clean up temporary file
        @unlink($tempFile);
    }
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

            // Get current content (draft if exists, otherwise live page)
            $currentContent = $pageManager->hasDraft($pageId)
                ? $pageManager->getDraft($pageId)
                : file_get_contents($pagePath);

            // Create a temporary file to work with BlockParser
            $tempFile = tempnam(sys_get_temp_dir(), 'cms_draft_');
            file_put_contents($tempFile, $currentContent);

            try {
                // Update the block in the temporary file
                $blockParser->updateBlock($tempFile, $blockName, $content, $custom);

                // Read the updated content
                $updatedContent = file_get_contents($tempFile);

                // Save as draft
                $pageManager->saveDraft($pageId, $updatedContent);

                // Create backup of live page (not draft)
                $backupManager->createBackup($pageId, $pagePath);

                echo json_encode(['success' => true, 'message' => 'Block updated and saved as draft']);
            } finally {
                // Clean up temporary file
                @unlink($tempFile);
            }
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

        case 'publish_page':
            $pageId = normalizePageId($input['page_id'] ?? '');

            if (!isset($input['page_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing page_id parameter']);
                exit;
            }

            try {
                $pageManager->publishDraft($pageId);
                echo json_encode(['success' => true, 'message' => 'Draft published successfully']);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        case 'discard_draft':
            $pageId = normalizePageId($input['page_id'] ?? '');

            if (!isset($input['page_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing page_id parameter']);
                exit;
            }

            try {
                $pageManager->discardDraft($pageId);
                echo json_encode(['success' => true, 'message' => 'Draft discarded successfully']);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
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

        case 'read_post':
            $collectionId = $input['collection_id'] ?? 'blog';
            $slug = $input['slug'] ?? '';

            if (!$slug) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing slug parameter']);
                exit;
            }

            try {
                // Check if draft exists first, otherwise use published
                $draftPath = $blogManager->getPostPath($collectionId, $slug, 'draft');
                $publishedPath = $blogManager->getPostPath($collectionId, $slug, 'published');

                if ($draftPath) {
                    $postPath = $draftPath;
                    $status = 'draft';
                } elseif ($publishedPath) {
                    $postPath = $publishedPath;
                    $status = 'published';
                } else {
                    throw new Exception("Post not found: {$slug}");
                }

                $content = file_get_contents($postPath);
                echo json_encode([
                    'success' => true,
                    'collection_id' => $collectionId,
                    'slug' => $slug,
                    'status' => $status,
                    'content' => $content
                ]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        case 'read_post_block':
            $collectionId = $input['collection_id'] ?? 'blog';
            $slug = $input['slug'] ?? '';
            $blockName = $input['block_name'] ?? '';

            if (!$slug) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing slug parameter']);
                exit;
            }

            if (!$blockName) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing block_name parameter']);
                exit;
            }

            try {
                // Check if draft exists first, otherwise use published
                $draftPath = $blogManager->getPostPath($collectionId, $slug, 'draft');
                $publishedPath = $blogManager->getPostPath($collectionId, $slug, 'published');

                if ($draftPath) {
                    $postPath = $draftPath;
                    $status = 'draft';
                } elseif ($publishedPath) {
                    $postPath = $publishedPath;
                    $status = 'published';
                } else {
                    throw new Exception("Post not found: {$slug}");
                }

                $blocks = $blockParser->parseBlocks($postPath);
                $foundBlock = null;

                foreach ($blocks as $block) {
                    if ($block['name'] === $blockName) {
                        $foundBlock = $block;
                        break;
                    }
                }

                if (!$foundBlock) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => "Block not found: {$blockName}"]);
                    exit;
                }

                echo json_encode([
                    'success' => true,
                    'collection_id' => $collectionId,
                    'slug' => $slug,
                    'status' => $status,
                    'block' => $foundBlock
                ]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        case 'update_post_block':
            $collectionId = $input['collection_id'] ?? 'blog';
            $slug = $input['slug'] ?? '';
            $blockName = $input['block_name'] ?? '';
            $newContent = $input['new_content'] ?? '';
            $customFlag = $input['custom'] ?? null;

            if (!$slug) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing slug parameter']);
                exit;
            }

            if (!$blockName) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing block_name parameter']);
                exit;
            }

            try {
                // Always save to draft (like pages)
                $draftPath = $blogManager->getPostPath($collectionId, $slug, 'draft');
                $publishedPath = $blogManager->getPostPath($collectionId, $slug, 'published');

                // If editing a published post and no draft exists, copy published to draft first
                if (!$draftPath && $publishedPath) {
                    $draftDir = $config['drafts_dir'] . '/' . $collectionId . '/' . $slug;

                    // Create draft directory
                    if (!is_dir($draftDir)) {
                        mkdir($draftDir, 0755, true);
                    }

                    // Copy published content to draft
                    copy($publishedPath, $draftDir . '/index.php');
                    $draftPath = $draftDir . '/index.php';
                }

                // Get the path to edit (draft if exists, otherwise error)
                $editPath = $draftPath ?: $publishedPath;

                if (!$editPath) {
                    throw new Exception("Post not found: {$slug}");
                }

                // Update the block
                $blockParser->updateBlock($editPath, $blockName, $newContent, $customFlag);

                echo json_encode([
                    'success' => true,
                    'collection_id' => $collectionId,
                    'slug' => $slug,
                    'block_name' => $blockName,
                    'status' => 'draft',
                    'message' => 'Block updated in draft. Use publish_post to make it live.'
                ]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        case 'find_and_replace_block_content':
            handleFindAndReplaceBlockContent($input, $pageManager, $blockParser, $backupManager);
            break;

        case 'insert_block':
            handleInsertBlock($input, $pageManager, $blockParser, $backupManager);
            break;

        case 'search_in_page':
            handleSearchInPage($input, $pageManager);
            break;

        case 'get_page_region':
            handleGetPageRegion($input, $pageManager);
            break;

        case 'update_page_region':
            handleUpdatePageRegion($input, $pageManager, $backupManager);
            break;

        case 'upload_file':
            $base64Data = $input['data'] ?? '';
            $filename = $input['filename'] ?? '';
            $subdir = $input['subdir'] ?? null;

            if (!$base64Data || !$filename) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required parameters: data, filename']);
                exit;
            }

            $result = $uploadManager->uploadFile($base64Data, $filename, $subdir);

            if ($result['success']) {
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
            break;

        case 'upload_image':
            $base64Data = $input['data'] ?? '';
            $filename = $input['filename'] ?? '';
            $subdir = $input['subdir'] ?? null;

            if (!$base64Data || !$filename) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required parameters: data, filename']);
                exit;
            }

            $result = $uploadManager->uploadImage($base64Data, $filename, $subdir);

            if ($result['success']) {
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
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
