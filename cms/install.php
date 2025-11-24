<?php
/**
 * Web Installer for MCP Flat-file CMS
 *
 * Modes:
 * - Install: Initial setup when config doesn't exist
 * - Reconfigure: Update settings when config exists
 */

// Check PHP version
$minPhpVersion = '8.0.0';
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    die("PHP {$minPhpVersion}+ is required. Current version: " . PHP_VERSION);
}

// Determine mode
$configPath = __DIR__ . '/config/config.php';
$usersPath = __DIR__ . '/config/users.json';
$isReconfigure = file_exists($configPath);

// Handle form submission
$success = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $siteName = $_POST['site_name'] ?? 'My Site';
        $adminEmail = $_POST['admin_email'] ?? '';
        $adminUsername = $_POST['admin_username'] ?? '';
        $adminPassword = $_POST['admin_password'] ?? '';
        $adminPasswordConfirm = $_POST['admin_password_confirm'] ?? '';
        $mcpToken = $_POST['mcp_token'] ?? '';
        $maxBackups = (int)($_POST['max_backups'] ?? 10);

        // Validate inputs
        if (!$adminEmail || !$adminUsername || (!$isReconfigure && !$adminPassword)) {
            throw new Exception('All fields are required');
        }

        if (!$isReconfigure && $adminPassword !== $adminPasswordConfirm) {
            throw new Exception('Passwords do not match');
        }

        // Generate MCP token if empty
        if (!$mcpToken) {
            $mcpToken = bin2hex(random_bytes(32));
        }

        // Create required directories
        $dirs = [
            __DIR__ . '/drafts',
            __DIR__ . '/backups',
            __DIR__ . '/logs',
            __DIR__ . '/modules',
            __DIR__ . '/config',
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    throw new Exception("Failed to create directory: {$dir}");
                }
            }
        }

        // Write config.php
        $rootDir = realpath(__DIR__ . '/..');
        $cmsDir = realpath(__DIR__);
        $draftsDir = realpath(__DIR__ . '/drafts');
        $backupsDir = realpath(__DIR__ . '/backups');

        $configContent = <<<PHP
<?php
/**
 * Core configuration for flat MCP CMS.
 */
return [
    // Token used by AI MCP clients (ChatGPT, Claude, etc.).
    'mcp_token' => '{$mcpToken}',

    // Directories
    'root_dir'    => '{$rootDir}',
    'cms_dir'     => '{$cmsDir}',
    'drafts_dir'  => '{$draftsDir}',
    'backups_dir' => '{$backupsDir}',

    // Backups
    'max_backups_per_page' => {$maxBackups},

    // Optional settings
    'site_name'  => '{$siteName}',
    'language'   => 'en',
];
PHP;

        if (file_put_contents($configPath, $configContent) === false) {
            throw new Exception('Failed to write config.php');
        }

        // Create or update users.json
        if ($isReconfigure && file_exists($usersPath)) {
            // Update existing user
            $usersData = json_decode(file_get_contents($usersPath), true);
            if (isset($usersData['users'][0])) {
                $usersData['users'][0]['email'] = $adminEmail;
                $usersData['users'][0]['username'] = $adminUsername;
                if ($adminPassword) {
                    $usersData['users'][0]['password_hash'] = password_hash($adminPassword, PASSWORD_DEFAULT);
                }
            }
        } else {
            // Create new users file
            $usersData = [
                'users' => [
                    [
                        'username' => $adminUsername,
                        'email' => $adminEmail,
                        'password_hash' => password_hash($adminPassword, PASSWORD_DEFAULT),
                        'role' => 'owner',
                    ],
                ],
            ];
        }

        if (file_put_contents($usersPath, json_encode($usersData, JSON_PRETTY_PRINT)) === false) {
            throw new Exception('Failed to write users.json');
        }

        // Create installation flag file
        $flagFile = __DIR__ . '/.installed';
        file_put_contents($flagFile, date('Y-m-d H:i:s'));

        $success = true;
        $generatedToken = $mcpToken;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Check if already installed using flag file
$flagFile = __DIR__ . '/.installed';
$isAlreadyInstalled = file_exists($flagFile) && !isset($_GET['reconfigure']);

// If reconfigure parameter is present, allow reinstallation
if (isset($_GET['reconfigure']) && file_exists($flagFile)) {
    // Remove flag to allow reconfiguration
    @unlink($flagFile);
}

// Pre-flight checks
$checks = [
    'PHP Version >= ' . $minPhpVersion => version_compare(PHP_VERSION, $minPhpVersion, '>='),
    'json_encode/decode available' => function_exists('json_encode') && function_exists('json_decode'),
    'password_hash/verify available' => function_exists('password_hash') && function_exists('password_verify'),
    'cms/config/ writable' => is_writable(__DIR__ . '/config') || (!file_exists(__DIR__ . '/config') && is_writable(__DIR__)),
    'cms/ writable' => is_writable(__DIR__),
];

$allChecksPassed = !in_array(false, $checks, true);

// Load existing config if reconfiguring
$existingConfig = [];
$existingUser = [];
if ($isReconfigure && file_exists($configPath)) {
    $existingConfig = include $configPath;
    if (file_exists($usersPath)) {
        $usersData = json_decode(file_get_contents($usersPath), true);
        $existingUser = $usersData['users'][0] ?? [];
    }
}

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $isReconfigure ? 'Reconfigure' : 'Install'; ?> - MCP Flat CMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen py-12 px-4">
    <div class="max-w-2xl mx-auto">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">
                <?php echo $isReconfigure ? 'Reconfigure' : 'Install'; ?> MCP Flat CMS
            </h1>
            <p class="text-gray-600">PHP-based flat-file CMS with MCP integration</p>
        </div>

        <?php if ($isAlreadyInstalled && !$success): ?>
            <div class="bg-white rounded-lg shadow-md p-8 mb-6">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mb-4">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900">Already Installed</h2>
                    <p class="text-gray-600 mt-2">The CMS is already installed and configured.</p>
                </div>

                <div class="space-y-4">
                    <p class="text-center text-gray-700">
                        <a href="/cms/admin/" class="inline-block px-6 py-3 bg-blue-600 text-white font-semibold rounded-md hover:bg-blue-700 transition">
                            Go to Admin Panel
                        </a>
                    </p>
                    <p class="text-center text-sm text-gray-600">
                        Need to change settings?
                        <a href="?reconfigure=1" class="text-blue-600 hover:text-blue-800">Reconfigure</a>
                    </p>
                </div>
            </div>
        <?php elseif ($success): ?>
            <div class="bg-white rounded-lg shadow-md p-8 mb-6">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900">Installation Successful!</h2>
                    <p class="text-gray-600 mt-2">Your CMS has been <?php echo $isReconfigure ? 'reconfigured' : 'installed'; ?> successfully.</p>
                </div>

                <div class="space-y-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Admin Access</h3>
                        <p class="text-gray-700">
                            <strong>Admin URL:</strong>
                            <a href="/cms/admin/" class="text-blue-600 hover:text-blue-800">/cms/admin/</a>
                        </p>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">MCP Configuration</h3>
                        <p class="text-gray-700 mb-2">
                            <strong>MCP Endpoint:</strong>
                        </p>
                        <code class="block bg-gray-100 p-3 rounded text-sm break-all">
                            <?php echo ($_SERVER['HTTPS'] ?? 'off') === 'on' ? 'https' : 'http'; ?>://<?php echo $_SERVER['HTTP_HOST']; ?>/cms/mcp/index.php
                        </code>
                        <p class="text-gray-700 mt-4 mb-2">
                            <strong>MCP Token (save this!):</strong>
                        </p>
                        <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4">
                            <code class="text-sm break-all"><?php echo htmlspecialchars($generatedToken); ?></code>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif ($error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
                <p class="text-red-700"><strong>Error:</strong> <?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
            <div class="bg-white rounded-lg shadow-md p-8 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Pre-flight Checks</h2>
                <div class="space-y-2">
                    <?php foreach ($checks as $name => $result): ?>
                        <div class="flex items-center">
                            <?php if ($result): ?>
                                <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            <?php else: ?>
                                <svg class="w-5 h-5 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            <?php endif; ?>
                            <span class="text-gray-700"><?php echo htmlspecialchars($name); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($allChecksPassed): ?>
                <div class="bg-white rounded-lg shadow-md p-8">
                    <form method="post" class="space-y-6">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Site Settings</h3>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Site Name:</label>
                                <input type="text" name="site_name" value="<?php echo htmlspecialchars($existingConfig['site_name'] ?? 'My Site'); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>

                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Admin User</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Email:</label>
                                    <input type="email" name="admin_email" value="<?php echo htmlspecialchars($existingUser['email'] ?? ''); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Username:</label>
                                    <input type="text" name="admin_username" value="<?php echo htmlspecialchars($existingUser['username'] ?? ''); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Password <?php echo $isReconfigure ? '(leave empty to keep current)' : ''; ?>:
                                    </label>
                                    <input type="password" name="admin_password" <?php echo !$isReconfigure ? 'required' : ''; ?> class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password:</label>
                                    <input type="password" name="admin_password_confirm" <?php echo !$isReconfigure ? 'required' : ''; ?> class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">MCP Settings</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">MCP Token:</label>
                                    <input type="text" name="mcp_token" value="<?php echo htmlspecialchars($existingConfig['mcp_token'] ?? ''); ?>" placeholder="Leave empty to auto-generate" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm">
                                    <p class="mt-1 text-sm text-gray-500">This token is used by AI clients (ChatGPT, Claude) to access the CMS via MCP.</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Max Backups per Page:</label>
                                    <input type="number" name="max_backups" value="<?php echo htmlspecialchars($existingConfig['max_backups_per_page'] ?? 10); ?>" min="1" max="100" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="w-full py-3 bg-blue-600 text-white font-semibold rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition">
                            <?php echo $isReconfigure ? 'Save Changes' : 'Install'; ?>
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4">
                    <p class="text-red-700">
                        <strong>Cannot proceed:</strong> Some pre-flight checks failed. Please fix the issues above and refresh this page.
                    </p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
