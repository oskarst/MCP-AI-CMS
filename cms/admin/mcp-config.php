<?php
/**
 * MCP Configuration Generator
 *
 * Generates a config.json file for ChatGPT Desktop and other MCP clients
 */

require_once __DIR__ . '/includes/auth-guard.php';
require_once __DIR__ . '/../core/CSRF.php';

// Handle token regeneration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'regenerate_token') {
    CSRF::verifyOrDie();

    try {
        // Generate new token
        $newToken = bin2hex(random_bytes(32));

        // Update config file
        $configPath = __DIR__ . '/../config/config.php';
        $configContent = file_get_contents($configPath);

        // Replace token in config
        $configContent = preg_replace(
            "/'mcp_token'\s*=>\s*'[^']*'/",
            "'mcp_token' => '{$newToken}'",
            $configContent
        );

        if (file_put_contents($configPath, $configContent) === false) {
            throw new Exception('Failed to update config file');
        }

        // Reload config
        $config = require $configPath;

        $successMessage = 'MCP token regenerated successfully. Download the new config below.';
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Handle config download
if (isset($_GET['download']) && $_GET['download'] === '1') {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $baseUrl = $protocol . '://' . $host . '/cms/mcp/index.php';

    $configJson = [
        'servers' => [
            'mcpcms' => [
                'type' => 'http',
                'base_url' => $baseUrl,
                'headers' => [
                    'X-CMS-MCP-TOKEN' => $config['mcp_token'],
                ],
                'tools' => [
                    [
                        'name' => 'list_pages',
                        'description' => 'List all available page_ids in the CMS. PRIMARY DISCOVERY TOOL: Use this FIRST to identify the correct page_id when the user references a page in natural language (e.g., "about page", "homepage", "contact"). If the page reference is ambiguous, ask the user to clarify which page they mean.',
                        'readOnlyHint' => true,
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => (object)[],
                            'required' => [],
                        ],
                    ],
                    [
                        'name' => 'list_blocks',
                        'description' => 'List all CMS blocks on a page (returns metadata only: name, role, custom). PREPARATION TOOL: Use this BEFORE editing to understand page structure and identify which blocks exist. Always prefer working with CMS blocks over raw page regions. This tool does not return block content, only structure.',
                        'readOnlyHint' => true,
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'page_id' => [
                                    'type' => 'string',
                                    'description' => 'Page ID (e.g., "about", "about/team"). For homepage use: "" or "/"',
                                ],
                            ],
                            'required' => ['page_id'],
                        ],
                    ],
                    [
                        'name' => 'update_block',
                        'description' => 'Update a single CMS block\'s content. DESTRUCTIVE: Use this ONLY after identifying the exact block via search_blocks or list_blocks. This replaces the entire block content. For small text changes within a block, prefer find_and_replace_block_content instead. Always work with CMS blocks when possible rather than raw page regions.',
                        'destructiveHint' => true,
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'page_id' => [
                                    'type' => 'string',
                                    'description' => 'Page ID. For homepage use: "" or "/"',
                                ],
                                'name' => [
                                    'type' => 'string',
                                    'description' => 'Block name',
                                ],
                                'content' => [
                                    'type' => 'string',
                                    'description' => 'New block content (HTML)',
                                ],
                                'custom' => [
                                    'type' => 'boolean',
                                    'description' => 'Whether this block is a custom per-page override',
                                ],
                            ],
                            'required' => ['page_id', 'name', 'content'],
                        ],
                    ],
                    [
                        'name' => 'duplicate_page',
                        'description' => 'Duplicate an existing page to create a new one. DESTRUCTIVE: Creates a new page by copying all content and blocks from an existing page. The new page will have its own independent copy of all blocks.',
                        'destructiveHint' => true,
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'source_page_id' => [
                                    'type' => 'string',
                                    'description' => 'Source page ID to duplicate from',
                                ],
                                'new_page_id' => [
                                    'type' => 'string',
                                    'description' => 'New page ID (e.g., "about", "services/web")',
                                ],
                            ],
                            'required' => ['source_page_id', 'new_page_id'],
                        ],
                    ],
                    [
                        'name' => 'delete_page',
                        'description' => 'Delete a page permanently. DESTRUCTIVE: This action cannot be undone. A backup is created before deletion. Use with caution.',
                        'destructiveHint' => true,
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'page_id' => [
                                    'type' => 'string',
                                    'description' => 'Page ID to delete',
                                ],
                            ],
                            'required' => ['page_id'],
                        ],
                    ],
                    [
                        'name' => 'list_backups',
                        'description' => 'List all available backups for a page with timestamps. READ-ONLY: Use this to discover available restore points before using restore_backup.',
                        'readOnlyHint' => true,
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'page_id' => [
                                    'type' => 'string',
                                    'description' => 'Page ID',
                                ],
                            ],
                            'required' => ['page_id'],
                        ],
                    ],
                    [
                        'name' => 'restore_backup',
                        'description' => 'Restore a page from a previous backup. DESTRUCTIVE: Replaces current page content with backed-up version. Creates a new backup of current state before restoring. Use list_backups first to identify the correct backup timestamp.',
                        'destructiveHint' => true,
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'page_id' => [
                                    'type' => 'string',
                                    'description' => 'Page ID',
                                ],
                                'timestamp' => [
                                    'type' => 'string',
                                    'description' => 'Backup timestamp (YmdHis format)',
                                ],
                            ],
                            'required' => ['page_id', 'timestamp'],
                        ],
                    ],
                    [
                        'name' => 'search_blocks',
                        'description' => '**PRIMARY SEARCH TOOL** - Search for text inside CMS blocks across all pages. MANDATORY WORKFLOW: (1) Use this FIRST when looking for any user-specified text. (2) If multiple blocks match → DO NOT guess, ASK THE USER to clarify which page/section. (3) If no results in case_insensitive mode → retry with html_insensitive mode (ignores HTML tags like <b>, <span>). (4) ONLY if still no results → warn user "This content is not in a CMS-managed block" and fallback to search_in_page. Returns: block_name, page_id, role, custom flag, content preview. Never skip this step before editing block content.',
                        'readOnlyHint' => true,
                        'usage_example' => 'CORRECT WORKFLOW: 1) search_blocks (case_insensitive), 2) if empty → retry with html_insensitive, 3) if multiple matches → ask user, 4) if still empty → warn and use search_in_page, 5) then use find_and_replace_block_content or update_block',
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'search_text' => [
                                    'type' => 'string',
                                    'description' => 'Text to search for in block content',
                                ],
                                'search_mode' => [
                                    'type' => 'string',
                                    'description' => 'Search mode: "case_insensitive" (default), "case_sensitive", or "html_insensitive" (ignores HTML tags)',
                                    'enum' => ['case_insensitive', 'case_sensitive', 'html_insensitive'],
                                ],
                            ],
                            'required' => ['search_text'],
                        ],
                        'disambiguation_required' => true,
                        'disambiguation_message' => 'Multiple blocks contain the same text. Ask the user which page/section is correct.',
                    ],
                    [
                        'name' => 'get_usage_tips',
                        'description' => 'Get helpful tips and best practices for using the CMS MCP tools effectively. READ-ONLY: Returns guidance on tool usage patterns and workflows.',
                        'readOnlyHint' => true,
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => (object)[],
                            'required' => [],
                        ],
                    ],
                    [
                        'name' => 'create_page',
                        'description' => 'Create a new page with optional HTML content. DESTRUCTIVE: Creates a new page file in the CMS. If content is not provided, creates a blank page. The new page will be accessible at the specified page_id URL.',
                        'destructiveHint' => true,
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'page_id' => [
                                    'type' => 'string',
                                    'description' => 'New page ID (e.g., "about", "services/web")',
                                ],
                                'content' => [
                                    'type' => 'string',
                                    'description' => 'Optional HTML content for the page. If empty, creates a blank page.',
                                ],
                            ],
                            'required' => ['page_id'],
                        ],
                    ],
                    [
                        'name' => 'read_page',
                        'description' => 'Read the full HTML content of a page file. READ-ONLY: Returns entire page HTML. Use this sparingly as it may consume significant context. Prefer list_blocks + read_block for structured access, or get_page_region for targeted reading.',
                        'readOnlyHint' => true,
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'page_id' => [
                                    'type' => 'string',
                                    'description' => 'Page ID. For homepage use: "" or "/"',
                                ],
                            ],
                            'required' => ['page_id'],
                        ],
                    ],
                    [
                        'name' => 'read_block',
                        'description' => 'Read a specific CMS block\'s content from a page. READ-ONLY: Returns only the requested block content. Use this after identifying the block via search_blocks or list_blocks. Preferred over read_page for inspecting block content before editing.',
                        'readOnlyHint' => true,
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'page_id' => [
                                    'type' => 'string',
                                    'description' => 'Page ID. For homepage use: "" or "/"',
                                ],
                                'name' => [
                                    'type' => 'string',
                                    'description' => 'Block name',
                                ],
                            ],
                            'required' => ['page_id', 'name'],
                        ],
                    ],
                    [
                        'name' => 'list_posts',
                        'description' => 'List all blog posts in a collection (blog, news, etc.) showing both drafts and published posts. READ-ONLY: Returns post metadata including slug, status (draft/published), and collection. Use this to discover available posts before editing.',
                        'readOnlyHint' => true,
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'collection_id' => [
                                    'type' => 'string',
                                    'description' => 'Collection ID (default: "blog")',
                                ],
                            ],
                            'required' => [],
                        ],
                    ],
                    [
                        'name' => 'create_post',
                        'description' => 'Create a new blog post as a draft. DESTRUCTIVE: Creates a new post file in the drafts folder. If content is not provided, uses default template with standard blocks (meta, navigation, content, footer). The post must be published separately using publish_post.',
                        'destructiveHint' => true,
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'collection_id' => [
                                    'type' => 'string',
                                    'description' => 'Collection ID (default: "blog")',
                                ],
                                'slug' => [
                                    'type' => 'string',
                                    'description' => 'Post slug (e.g., "my-first-post")',
                                ],
                                'content' => [
                                    'type' => 'string',
                                    'description' => 'Optional HTML content for the post. If empty, creates default template.',
                                ],
                            ],
                            'required' => ['slug'],
                        ],
                    ],
                    [
                        'name' => 'publish_post',
                        'description' => 'Publish a draft blog post (move from drafts folder to public folder). DESTRUCTIVE: Makes the post publicly accessible. The post must exist as a draft first. This action moves the post file from /cms/drafts/ to the public collection folder.',
                        'destructiveHint' => true,
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'collection_id' => [
                                    'type' => 'string',
                                    'description' => 'Collection ID (default: "blog")',
                                ],
                                'slug' => [
                                    'type' => 'string',
                                    'description' => 'Post slug',
                                ],
                            ],
                            'required' => ['slug'],
                        ],
                    ],
                    [
                        'name' => 'unpublish_post',
                        'description' => 'Unpublish a blog post (move from public folder back to drafts). DESTRUCTIVE: Removes the post from public access and moves it back to the drafts folder. The post must be published first. Useful for taking down content temporarily or making major revisions.',
                        'destructiveHint' => true,
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'collection_id' => [
                                    'type' => 'string',
                                    'description' => 'Collection ID (default: "blog")',
                                ],
                                'slug' => [
                                    'type' => 'string',
                                    'description' => 'Post slug',
                                ],
                            ],
                            'required' => ['slug'],
                        ],
                    ],
                    [
                        'name' => 'find_and_replace_block_content',
                        'description' => 'Find and replace text inside a CMS block without sending full block content. DESTRUCTIVE: Use this ONLY after identifying the exact block via search_blocks. PREFERRED for small textual edits inside CMS blocks (e.g., fix typo, update name, change phone number). Server handles the replacement - DO NOT fetch/send full block content. Always prefer this over update_block for small text changes. Workflow: 1) search_blocks to find block, 2) call this tool with exact search/replace strings. If no match found, returns replacements=0 without modifying file.',
                        'destructiveHint' => true,
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'page_id' => [
                                    'type' => 'string',
                                    'description' => 'Page ID. For homepage use: "" or "/"',
                                ],
                                'name' => [
                                    'type' => 'string',
                                    'description' => 'Block name',
                                ],
                                'search' => [
                                    'type' => 'string',
                                    'description' => 'Exact text to search for in the block',
                                ],
                                'replace' => [
                                    'type' => 'string',
                                    'description' => 'Replacement text',
                                ],
                                'mode' => [
                                    'type' => 'string',
                                    'description' => 'Replace mode: "first" (default) or "all"',
                                    'enum' => ['first', 'all'],
                                ],
                                'case_sensitive' => [
                                    'type' => 'boolean',
                                    'description' => 'Case sensitive search (default: true)',
                                ],
                            ],
                            'required' => ['page_id', 'name', 'search', 'replace'],
                        ],
                    ],
                    [
                        'name' => 'insert_block',
                        'description' => 'Insert a new CMS block into a page at a specific position. DESTRUCTIVE: Use this to add new content sections to a page. Always prefer this over region-based insertion when adding structured content. Workflow: 1) list_pages to identify page, 2) list_blocks to determine position, 3) insert_block with position (before_block/after_block/at_end), unique block name, role, and HTML content. DO NOT send or reconstruct entire page - only provide the new block content. This creates proper CMS block markers and leaves all other blocks unchanged.',
                        'destructiveHint' => true,
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'page_id' => [
                                    'type' => 'string',
                                    'description' => 'Page ID. For homepage use: "" or "/"',
                                ],
                                'position' => [
                                    'type' => 'object',
                                    'description' => 'Position where to insert the block',
                                    'properties' => [
                                        'type' => [
                                            'type' => 'string',
                                            'description' => 'Position type',
                                            'enum' => ['before_block', 'after_block', 'at_end'],
                                        ],
                                        'block_name' => [
                                            'type' => 'string',
                                            'description' => 'Reference block name (required for before_block/after_block)',
                                        ],
                                    ],
                                    'required' => ['type'],
                                ],
                                'name' => [
                                    'type' => 'string',
                                    'description' => 'New block name (must be unique on the page)',
                                ],
                                'role' => [
                                    'type' => 'string',
                                    'description' => 'Optional block role (e.g., "meta", "navigation")',
                                ],
                                'custom' => [
                                    'type' => 'boolean',
                                    'description' => 'Whether this is a custom block (default: false)',
                                ],
                                'content' => [
                                    'type' => 'string',
                                    'description' => 'HTML content for the new block',
                                ],
                            ],
                            'required' => ['page_id', 'position', 'name', 'content'],
                        ],
                    ],
                    [
                        'name' => 'search_in_page',
                        'description' => '**RAW FILE SEARCH - FALLBACK ONLY** - Search within the raw page file by line. READ-ONLY: Use this ONLY if search_blocks (normal + html_insensitive modes) finds nothing. MANDATORY: Before using this tool, WARN THE USER: "This content is not inside a CMS-managed block; I can still edit it, but it will be outside the CMS block system." Never use this as a first step - always try search_blocks first. Returns line ranges with short snippets only, not the whole page.',
                        'readOnlyHint' => true,
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'page_id' => [
                                    'type' => 'string',
                                    'description' => 'Page ID. For homepage use: "" or "/"',
                                ],
                                'search' => [
                                    'type' => 'string',
                                    'description' => 'Text to search for in the page',
                                ],
                                'limit' => [
                                    'type' => 'integer',
                                    'description' => 'Max number of matches to return (default: 20)',
                                ],
                                'case_sensitive' => [
                                    'type' => 'boolean',
                                    'description' => 'Case sensitive search (default: false)',
                                ],
                            ],
                            'required' => ['page_id', 'search'],
                        ],
                    ],
                    [
                        'name' => 'get_page_region',
                        'description' => 'Retrieve a small region of a page by line range. READ-ONLY: Use ONLY after warning user about non-block editing and confirming it is desired. This is for editing layout/code outside CMS blocks. Use after search_in_page to get the specific lines to edit. NEVER return full file. NEVER use region tools to inspect or modify CMS:BLOCK or CMS:WRAP markers - those are managed by block tools.',
                        'readOnlyHint' => true,
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'page_id' => [
                                    'type' => 'string',
                                    'description' => 'Page ID. For homepage use: "" or "/"',
                                ],
                                'start_line' => [
                                    'type' => 'integer',
                                    'description' => '1-based line number (inclusive)',
                                ],
                                'end_line' => [
                                    'type' => 'integer',
                                    'description' => '1-based line number (inclusive)',
                                ],
                                'max_chars' => [
                                    'type' => 'integer',
                                    'description' => 'Soft cap on region length in characters (default: 4000)',
                                ],
                            ],
                            'required' => ['page_id', 'start_line', 'end_line'],
                        ],
                    ],
                    [
                        'name' => 'update_page_region',
                        'description' => 'Apply a patch to a page region using optimistic locking. DESTRUCTIVE: Use ONLY after get_page_region. FORBIDDEN: MUST NOT modify or remove CMS:BLOCK or CMS:WRAP markers - those are sacred CMS infrastructure. Keep edits minimal to layout/HTML/CSS/JS only. Optimistic locking means the update ONLY succeeds if old_region still matches current file content. If region changed, the tool fails and you must re-fetch with get_page_region.',
                        'destructiveHint' => true,
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'page_id' => [
                                    'type' => 'string',
                                    'description' => 'Page ID. For homepage use: "" or "/"',
                                ],
                                'start_line' => [
                                    'type' => 'integer',
                                    'description' => '1-based line number where old_region originally started',
                                ],
                                'end_line' => [
                                    'type' => 'integer',
                                    'description' => '1-based line number where old_region originally ended',
                                ],
                                'old_region' => [
                                    'type' => 'string',
                                    'description' => 'EXACT content that was returned from get_page_region',
                                ],
                                'new_region' => [
                                    'type' => 'string',
                                    'description' => 'The edited content that should replace old_region',
                                ],
                            ],
                            'required' => ['page_id', 'start_line', 'end_line', 'old_region', 'new_region'],
                        ],
                    ],
                    [
                        'name' => 'publish_page',
                        'description' => 'Publish a page draft to make it live. DESTRUCTIVE: Moves the draft content to the live page, making it publicly accessible. The page must have a draft saved first (from any edit operation). This action replaces the current live page with the draft version.',
                        'destructiveHint' => true,
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'page_id' => [
                                    'type' => 'string',
                                    'description' => 'Page ID. For homepage use: "" or "/"',
                                ],
                            ],
                            'required' => ['page_id'],
                        ],
                    ],
                    [
                        'name' => 'discard_draft',
                        'description' => 'Discard a page draft without publishing. DESTRUCTIVE: Permanently deletes the draft version, keeping the live page unchanged. Use this to abandon unpublished changes. Cannot be undone.',
                        'destructiveHint' => true,
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'page_id' => [
                                    'type' => 'string',
                                    'description' => 'Page ID. For homepage use: "" or "/"',
                                ],
                            ],
                            'required' => ['page_id'],
                        ],
                    ],
                ],
            ],
        ],
    ];

    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="cms-mcp-config.json"');
    echo json_encode($configJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$pageTitle = 'MCP Configuration';
$activePage = 'settings';

require __DIR__ . '/includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900 mb-2">MCP Configuration</h1>
    <p class="text-gray-600">
        <a href="/cms/admin/" class="text-blue-600 hover:text-blue-800">&larr; Back to Dashboard</a>
    </p>
</div>

<?php if (isset($successMessage)): ?>
    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
        <p class="text-green-700"><?php echo htmlspecialchars($successMessage); ?></p>
    </div>
<?php endif; ?>

<?php if (isset($errorMessage)): ?>
    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
        <p class="text-red-700"><?php echo htmlspecialchars($errorMessage); ?></p>
    </div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-4">MCP Endpoint Configuration</h2>

    <p class="text-gray-600 mb-6">Use this configuration to connect AI clients (ChatGPT Desktop, Claude, etc.) to your CMS via MCP.</p>

    <?php
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $baseUrl = $protocol . '://' . $host . '/cms/mcp/index.php';
    ?>

    <div class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">MCP Endpoint URL:</label>
            <input type="text" value="<?php echo htmlspecialchars($baseUrl); ?>" readonly onclick="this.select()" class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 font-mono text-sm">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">MCP Token:</label>
            <input type="text" value="<?php echo htmlspecialchars(substr($config['mcp_token'], 0, 16) . '...'); ?>" readonly class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 font-mono text-sm">
            <p class="mt-1 text-sm text-gray-500">Token is partially hidden for security. Download the config file to get the complete token.</p>
        </div>

        <div class="flex gap-3 pt-2">
            <a href="?download=1" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">Download MCP Config (config.json)</a>

            <form method="post" class="inline" onsubmit="return confirm('This will invalidate your current MCP configuration. Continue?');">
                <?php echo CSRF::inputField(); ?>
                <input type="hidden" name="action" value="regenerate_token">
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition">Regenerate Token</button>
            </form>
        </div>
    </div>
</div>

<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-4">Installation Instructions (ChatGPT Desktop - macOS)</h2>

    <ol class="list-decimal list-inside space-y-2 text-gray-700">
        <li>Download the MCP config file using the button above</li>
        <li>Open Finder and press <code class="bg-gray-100 px-1 py-0.5 rounded">Cmd + Shift + G</code></li>
        <li>Enter: <code class="bg-gray-100 px-1 py-0.5 rounded">~/Library/Application Support/OpenAI/ChatGPT/mcp</code></li>
        <li>If the folder doesn't exist, create it manually</li>
        <li>Copy the downloaded <code class="bg-gray-100 px-1 py-0.5 rounded">cms-mcp-config.json</code> file into this folder</li>
        <li>Rename it to <code class="bg-gray-100 px-1 py-0.5 rounded">config.json</code> (or merge with existing config)</li>
        <li>Restart ChatGPT Desktop</li>
    </ol>

    <div class="mt-4 bg-yellow-50 border-l-4 border-yellow-500 p-4">
        <p class="text-yellow-700"><strong>Security Warning:</strong> The config file contains your private MCP token. Keep it secure and never share it publicly.</p>
    </div>
</div>

<div class="bg-white rounded-lg shadow-md p-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-4">Available MCP Tools</h2>

    <p class="text-gray-600 mb-4">Once configured, AI clients will have access to these tools:</p>

    <ul class="list-disc list-inside space-y-2 text-gray-700">
        <li><code class="bg-gray-100 px-1 py-0.5 rounded">list_pages</code> - List all pages in the CMS</li>
        <li><code class="bg-gray-100 px-1 py-0.5 rounded">list_blocks</code> - List editable blocks within a page (returns only metadata: name, role, custom)</li>
        <li><code class="bg-gray-100 px-1 py-0.5 rounded">search_blocks</code> - Search for blocks containing specific text with 3 modes: case_insensitive (default), case_sensitive, html_insensitive</li>
        <li><code class="bg-gray-100 px-1 py-0.5 rounded">read_page</code> - Read the full HTML content of a page</li>
        <li><code class="bg-gray-100 px-1 py-0.5 rounded">read_block</code> - Read a specific block's content from a page</li>
        <li><code class="bg-gray-100 px-1 py-0.5 rounded">create_page</code> - Create a new page with optional HTML content</li>
        <li><code class="bg-gray-100 px-1 py-0.5 rounded">update_block</code> - Update a block's content</li>
        <li><code class="bg-gray-100 px-1 py-0.5 rounded">find_and_replace_block_content</code> - Find and replace text in a block without sending full content</li>
        <li><code class="bg-gray-100 px-1 py-0.5 rounded">insert_block</code> - Insert a new block at a specific position (before/after block or at end)</li>
        <li><code class="bg-gray-100 px-1 py-0.5 rounded">search_in_page</code> - Search within a page and return line ranges with snippets</li>
        <li><code class="bg-gray-100 px-1 py-0.5 rounded">get_page_region</code> - Get a specific region of a page by line range</li>
        <li><code class="bg-gray-100 px-1 py-0.5 rounded">update_page_region</code> - Update a page region using optimistic locking</li>
        <li><code class="bg-gray-100 px-1 py-0.5 rounded">publish_page</code> - Publish a page draft to make it live</li>
        <li><code class="bg-gray-100 px-1 py-0.5 rounded">discard_draft</code> - Discard a page draft without publishing</li>
        <li><code class="bg-gray-100 px-1 py-0.5 rounded">duplicate_page</code> - Create a new page by duplicating an existing one</li>
        <li><code class="bg-gray-100 px-1 py-0.5 rounded">delete_page</code> - Delete a page</li>
        <li><code class="bg-gray-100 px-1 py-0.5 rounded">list_backups</code> - List backups for a page</li>
        <li><code class="bg-gray-100 px-1 py-0.5 rounded">restore_backup</code> - Restore a page from backup</li>
        <li><code class="bg-gray-100 px-1 py-0.5 rounded">list_posts</code> - List all posts in a collection (blog, news)</li>
        <li><code class="bg-gray-100 px-1 py-0.5 rounded">create_post</code> - Create a new draft blog post</li>
        <li><code class="bg-gray-100 px-1 py-0.5 rounded">publish_post</code> - Publish a draft post</li>
        <li><code class="bg-gray-100 px-1 py-0.5 rounded">unpublish_post</code> - Unpublish a post back to drafts</li>
        <li><code class="bg-gray-100 px-1 py-0.5 rounded">get_usage_tips</code> - Get helpful tips for using CMS tools effectively</li>
    </ul>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
