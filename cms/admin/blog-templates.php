<?php
/**
 * Blog Templates Settings
 * Edits template files directly: blog-post.php, blog-list.php, blog-item.php
 */

require_once __DIR__ . '/includes/auth-guard.php';
require_once __DIR__ . '/../core/CSRF.php';

$templatesDir = __DIR__ . '/../blog-templates';
$configFile = __DIR__ . '/../config/blog-templates.json';

// Template files
$postTemplateFile = $templatesDir . '/blog-post.php';
$listTemplateFile = $templatesDir . '/blog-list.php';
$itemTemplateFile = $templatesDir . '/blog-item.php';

// Handle template updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verifyOrDie();

    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_templates') {
            // Save template files
            $postTemplate = $_POST['post_template'] ?? '';
            $listTemplate = $_POST['list_template'] ?? '';
            $itemTemplate = $_POST['item_template'] ?? '';

            file_put_contents($postTemplateFile, $postTemplate);
            file_put_contents($listTemplateFile, $listTemplate);
            file_put_contents($itemTemplateFile, $itemTemplate);

            $successMessage = 'Templates saved successfully.';
        } elseif ($action === 'save_defaults') {
            // Save default values
            $config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];

            $config['defaults'] = [
                'author' => $_POST['default_author'] ?? 'Dev Team',
                'excerpt' => $_POST['default_excerpt'] ?? 'Read this article on our blog.',
                'read_time' => $_POST['default_read_time'] ?? '5 min read'
            ];

            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $successMessage = 'Default values saved successfully.';
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Load current templates from files
$postTemplate = file_exists($postTemplateFile) ? file_get_contents($postTemplateFile) : '';
$listTemplate = file_exists($listTemplateFile) ? file_get_contents($listTemplateFile) : '';
$itemTemplate = file_exists($itemTemplateFile) ? file_get_contents($itemTemplateFile) : '';

// Load config for defaults
$config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
$defaults = $config['defaults'] ?? [
    'author' => 'Dev Team',
    'excerpt' => 'Read this article on the Developers Alliance blog.',
    'read_time' => '5 min read'
];

$pageTitle = 'Blog Templates';
$activePage = 'settings';

require __DIR__ . '/includes/header.php';
?>

<h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-6">Blog Templates</h1>

<?php if (isset($successMessage)): ?>
    <div class="bg-green-50 dark:bg-green-900/20 border-l-4 border-green-500 p-4 mb-6">
        <p class="text-green-700 dark:text-green-400"><?php echo htmlspecialchars($successMessage); ?></p>
    </div>
<?php endif; ?>

<?php if (isset($errorMessage)): ?>
    <div class="bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 p-4 mb-6">
        <p class="text-red-700 dark:text-red-400"><?php echo htmlspecialchars($errorMessage); ?></p>
    </div>
<?php endif; ?>

<div class="mb-6">
    <a href="/cms/admin/settings.php" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">&larr; Back to Settings</a>
</div>

<!-- Default Values -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Default Values</h2>
    <form method="post">
        <?php echo CSRF::inputField(); ?>
        <input type="hidden" name="action" value="save_defaults">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Default Author</label>
                <input type="text" name="default_author" value="<?php echo htmlspecialchars($defaults['author']); ?>"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Default Excerpt</label>
                <input type="text" name="default_excerpt" value="<?php echo htmlspecialchars($defaults['excerpt']); ?>"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Default Read Time</label>
                <input type="text" name="default_read_time" value="<?php echo htmlspecialchars($defaults['read_time']); ?>"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
            </div>
        </div>

        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
            Save Defaults
        </button>
    </form>
</div>

<!-- Template Files -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Template Files</h2>

    <div class="mb-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
        <p class="text-sm text-blue-800 dark:text-blue-300 mb-2"><strong>Available Placeholders:</strong></p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm text-blue-700 dark:text-blue-400">
            <div>
                <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">{TITLE}</code> - Post title<br>
                <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">{SLUG}</code> - URL slug<br>
                <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">{DATE}</code> - Date (Y-m-d)<br>
                <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">{DATE_FORMATTED}</code> - Date (December 14, 2025)
            </div>
            <div>
                <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">{EXCERPT}</code> - Post excerpt<br>
                <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">{AUTHOR_NAME}</code> - Author name<br>
                <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">{FEATURED_IMAGE}</code> - Image URL<br>
                <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">{READING_TIME}</code> - Read time (minutes)
            </div>
        </div>
    </div>

    <form method="post" class="space-y-8">
        <?php echo CSRF::inputField(); ?>
        <input type="hidden" name="action" value="save_templates">

        <!-- Post Item Template -->
        <div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Post Item Template</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">cms/blog-templates/blog-item.php</code> -
                Used for each post card in the blog listing.
            </p>
            <textarea
                name="item_template"
                rows="15"
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-mono text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            ><?php echo htmlspecialchars($itemTemplate); ?></textarea>
        </div>

        <hr class="border-gray-200 dark:border-gray-700">

        <!-- List Page Template -->
        <div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Blog List Page Template</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">cms/blog-templates/blog-list.php</code> -
                Full page template for blog index. Use <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">{POSTS_LIST}</code> where posts should appear.
            </p>
            <textarea
                name="list_template"
                rows="20"
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-mono text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            ><?php echo htmlspecialchars($listTemplate); ?></textarea>
        </div>

        <hr class="border-gray-200 dark:border-gray-700">

        <!-- Post Template -->
        <div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Individual Blog Post Template</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">cms/blog-templates/blog-post.php</code> -
                Template used when creating new blog posts.
            </p>
            <textarea
                name="post_template"
                rows="25"
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-mono text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            ><?php echo htmlspecialchars($postTemplate); ?></textarea>
        </div>

        <div class="pt-4">
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                Save All Templates
            </button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
