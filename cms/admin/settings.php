<?php
/**
 * Admin Settings / Configuration
 */

require_once __DIR__ . '/includes/auth-guard.php';
require_once __DIR__ . '/../core/CSRF.php';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verifyOrDie();

    try {
        $siteName = $_POST['site_name'] ?? 'My Site';
        $maxBackups = (int)($_POST['max_backups'] ?? 10);
        $reservedFolders = $_POST['reserved_folders'] ?? 'cms,blog,assets,uploads,index';

        // Load current config
        $configPath = __DIR__ . '/../config/config.php';
        $currentConfig = require $configPath;

        // Update config
        $configContent = <<<PHP
<?php
/**
 * Core configuration for flat MCP CMS.
 */
return [
    // Token used by AI MCP clients (ChatGPT, Claude, etc.).
    'mcp_token' => '{$currentConfig['mcp_token']}',

    // Directories
    'root_dir'    => '{$currentConfig['root_dir']}',
    'cms_dir'     => '{$currentConfig['cms_dir']}',
    'drafts_dir'  => '{$currentConfig['drafts_dir']}',
    'backups_dir' => '{$currentConfig['backups_dir']}',

    // Backups
    'max_backups_per_page' => {$maxBackups},

    // Reserved folder names (cannot be used as page IDs)
    'reserved_folders' => explode(',', '{$reservedFolders}'),

    // Optional settings
    'site_name'  => '{$siteName}',
    'language'   => 'en',
];
PHP;

        if (file_put_contents($configPath, $configContent) === false) {
            throw new Exception('Failed to update config file');
        }

        // Reload config
        $config = require $configPath;

        $successMessage = 'Settings updated successfully.';
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Get current settings
$reservedFoldersString = is_array($config['reserved_folders'] ?? null)
    ? implode(',', $config['reserved_folders'])
    : 'cms,blog,assets,uploads,index';

$pageTitle = 'Settings';
$activePage = 'settings';

require __DIR__ . '/includes/header.php';
?>

<h1 class="text-3xl font-bold text-gray-900 mb-6">Settings</h1>

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

<div class="bg-white rounded-lg shadow-md p-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-6">General Configuration</h2>

    <form method="post" class="space-y-6">
        <?php echo CSRF::inputField(); ?>

        <div>
            <h3 class="text-lg font-medium text-gray-900 mb-4">Site Information</h3>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Site Name:
                </label>
                <input
                    type="text"
                    name="site_name"
                    value="<?php echo htmlspecialchars($config['site_name'] ?? 'My Site'); ?>"
                    required
                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                <p class="mt-1 text-sm text-gray-500">Display name for your website</p>
            </div>
        </div>

        <hr class="border-gray-200">

        <div>
            <h3 class="text-lg font-medium text-gray-900 mb-4">Backup Settings</h3>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Maximum Backups per Page:
                </label>
                <input
                    type="number"
                    name="max_backups"
                    value="<?php echo htmlspecialchars($config['max_backups_per_page'] ?? 10); ?>"
                    min="1"
                    max="100"
                    required
                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                <p class="mt-1 text-sm text-gray-500">Number of backup versions to keep for each page (older backups are automatically deleted)</p>
            </div>
        </div>

        <hr class="border-gray-200">

        <div>
            <h3 class="text-lg font-medium text-gray-900 mb-4">System Protection</h3>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Reserved Folder Names:
                </label>
                <input
                    type="text"
                    name="reserved_folders"
                    value="<?php echo htmlspecialchars($reservedFoldersString); ?>"
                    required
                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                >
                <p class="mt-1 text-sm text-gray-500">Comma-separated list of folder names that cannot be used as page IDs (e.g., cms,blog,assets,uploads)</p>

                <div class="mt-3 bg-yellow-50 border-l-4 border-yellow-500 p-4">
                    <p class="text-sm text-yellow-700">
                        <strong>Important:</strong> These folders are protected from being created as pages to prevent conflicts with system directories and media folders.
                    </p>
                </div>
            </div>
        </div>

        <div class="pt-4">
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                Save Settings
            </button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
