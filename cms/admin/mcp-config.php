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
            'flatcms' => [
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
                        'description' => 'List all editable blocks within a page',
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'page_id' => [
                                    'type' => 'string',
                                    'description' => 'Page ID (e.g., "", "about", "about/team")',
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
                                    'description' => 'Page ID',
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
        <li><code class="bg-gray-100 px-1 py-0.5 rounded">list_blocks</code> - List editable blocks within a page</li>
        <li><code class="bg-gray-100 px-1 py-0.5 rounded">update_block</code> - Update a block's content</li>
        <li><code class="bg-gray-100 px-1 py-0.5 rounded">duplicate_page</code> - Create a new page by duplicating an existing one</li>
        <li><code class="bg-gray-100 px-1 py-0.5 rounded">delete_page</code> - Delete a page</li>
        <li><code class="bg-gray-100 px-1 py-0.5 rounded">list_backups</code> - List backups for a page</li>
        <li><code class="bg-gray-100 px-1 py-0.5 rounded">restore_backup</code> - Restore a page from backup</li>
    </ul>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
