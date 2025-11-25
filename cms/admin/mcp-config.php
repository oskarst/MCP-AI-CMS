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
                        'description' => 'List all pages in the flat-file CMS',
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => (object)[],
                            'required' => [],
                        ],
                    ],
                    [
                        'name' => 'list_blocks',
                        'description' => 'List all editable blocks within a page. Returns only metadata (name, role, custom) without content. Use this to discover which blocks exist on a page.',
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
                        'description' => 'Update a single block\'s content on a page',
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
                        'description' => 'Duplicate an existing page to create a new one',
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
                        'description' => 'Delete a page',
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
                        'description' => 'List all backups for a page',
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
                        'description' => 'Restore a page from a backup',
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
                        'description' => 'Search for blocks containing the given text across all pages. Use this tool to find the right block when you need to locate specific content. Returns block_name, page_id, role, custom flag, and content preview. Use this FIRST before update_block or find_and_replace_block_content to identify the correct block location. Common pitfall: Don\'t assume text location - always search first. Search modes: case_insensitive (default) ignores case, case_sensitive matches exact case, html_insensitive ignores HTML tags (e.g., "Developers Alliance" matches "Developers <b>Alliance</b>"). Strategy: If text is not found with default case_insensitive mode, try again with html_insensitive mode as HTML tags may be breaking up the text.',
                        'usage_example' => 'To change text: 1) search_blocks to find it, 2) if no results, try with html_insensitive mode, 3) ask user if multiple matches, 4) update_block or find_and_replace_block_content',
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
                        'description' => 'Get helpful tips for using the CMS MCP tools effectively',
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => (object)[],
                            'required' => [],
                        ],
                    ],
                    [
                        'name' => 'create_page',
                        'description' => 'Create a new page with optional HTML content',
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
                        'description' => 'Read the full HTML content of a page',
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
                        'description' => 'Read a specific block\'s content from a page',
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
                        'description' => 'List all posts in a collection (blog, news, etc.)',
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
                        'description' => 'Create a new draft blog post',
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
                        'description' => 'Publish a draft post (move from drafts to public folder)',
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
                        'description' => 'Unpublish a post (move from public folder back to drafts)',
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
                        'description' => 'Find and replace a specific piece of text inside a block of a page without sending the full block content to the model. Use this tool when the user wants to update a small part of a long block (e.g., replace a name, fix a typo, update a phone number). Do NOT attempt to fetch or send the entire block content. Instead: 1. Determine the correct page_id and block name using list_pages, list_blocks or search_blocks. 2. Call find_and_replace_block_content with: page_id, block name, exact string to search, replacement string. 3. Prefer mode=\'first\' unless the user explicitly requests replacing all occurrences. 4. If no occurrence is found, do not modify the file and return replacements = 0.',
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
                        'description' => 'Insert a new CMS block into a page at a specific position (before or after another block, or at the end of the page) without sending or modifying the entire page content. Use this tool when the user wants to add a new section (e.g., promo, banner, note) to an existing page. Steps: 1. Determine the correct page_id using list_pages (e.g., \'en/about\' or \'lv/home\'). 2. Determine the correct insertion position by inspecting the existing blocks with list_blocks: Insert before a named block, Insert after a named block, Or insert at the end of the page. 3. Call insert_block with: page_id, position (before_block/after_block/at_end), name (unique block name), optional role (e.g. \'meta\' or \'navigation\'), custom flag, and the HTML content of the new block. 4. Do NOT try to send or reconstruct the entire page. This tool only inserts a new block and leaves all other blocks unchanged.',
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
                        'description' => 'Search for occurrences of a text string inside a single page and return line ranges with short snippets. Use this tool to locate where something is in the file (e.g., a paragraph, a footer, a script) before requesting a specific region with get_page_region or editing it with block-based tools. This tool does NOT return the whole page, only small snippets and line info.',
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
                        'description' => 'Retrieve a small region of a page by line range so you can inspect and edit part of a large file without loading the entire page. Use this tool after search_in_page or when you know the approximate line range you want to modify. The response includes only the requested region (not the whole file) plus the actual line numbers used.',
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
                        'description' => 'Replace a specific region of a page with edited content, using optimistic locking to avoid overwriting concurrent changes. Use this tool after get_page_region: send back the original region you received plus the updated region you want to apply. The server will only apply the change if the original region still matches the file; otherwise it will fail with an error so you can re-fetch and re-apply.',
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
$activePage = 'mcp';

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
