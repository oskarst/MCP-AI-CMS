<?php
/**
 * Blog Templates Settings
 */

require_once __DIR__ . '/includes/auth-guard.php';
require_once __DIR__ . '/../core/CSRF.php';

$templatesFile = __DIR__ . '/../config/blog-templates.json';

// Handle template updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verifyOrDie();

    try {
        $postTemplate = $_POST['post_template'] ?? '';
        $listTemplate = $_POST['list_template'] ?? '';
        $postItemTemplate = $_POST['post_item_template'] ?? '';

        $templates = [
            'post_template' => $postTemplate,
            'list_template' => $listTemplate,
            'post_item_template' => $postItemTemplate,
        ];

        if (file_put_contents($templatesFile, json_encode($templates, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
            throw new Exception('Failed to save templates');
        }

        $successMessage = 'Blog templates updated successfully.';
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Load current templates
$templates = [];
if (file_exists($templatesFile)) {
    $templates = json_decode(file_get_contents($templatesFile), true) ?? [];
}

$pageTitle = 'Blog Templates';
$activePage = 'settings';

require __DIR__ . '/includes/header.php';
?>

<h1 class="text-3xl font-bold text-gray-900 mb-6">Blog Templates</h1>

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
    <div class="mb-6">
        <a href="/cms/admin/settings.php" class="text-blue-600 hover:text-blue-800">&larr; Back to Settings</a>
    </div>

    <form method="post" class="space-y-8">
        <?php echo CSRF::inputField(); ?>

        <!-- Post Template -->
        <div>
            <h3 class="text-lg font-medium text-gray-900 mb-4">Individual Blog Post Template</h3>
            <p class="text-sm text-gray-600 mb-2">Template used when creating new blog posts. Available placeholders:</p>
            <ul class="text-sm text-gray-600 mb-4 list-disc list-inside">
                <li><code class="bg-gray-100 px-1 py-0.5 rounded">{TITLE}</code> - Post title (from slug)</li>
                <li><code class="bg-gray-100 px-1 py-0.5 rounded">{DATE}</code> - Current date</li>
                <li><code class="bg-gray-100 px-1 py-0.5 rounded">{COLLECTION_LABEL}</code> - Collection name (e.g., "Blog")</li>
            </ul>

            <textarea
                name="post_template"
                rows="20"
                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                required
            ><?php echo htmlspecialchars($templates['post_template'] ?? ''); ?></textarea>
        </div>

        <hr class="border-gray-200">

        <!-- List Page Template -->
        <div>
            <h3 class="text-lg font-medium text-gray-900 mb-4">Blog List Page Template</h3>
            <p class="text-sm text-gray-600 mb-2">Template for the blog index page that lists all posts. Available placeholders:</p>
            <ul class="text-sm text-gray-600 mb-4 list-disc list-inside">
                <li><code class="bg-gray-100 px-1 py-0.5 rounded">{COLLECTION_LABEL}</code> - Collection name</li>
                <li><code class="bg-gray-100 px-1 py-0.5 rounded">{POSTS_LIST}</code> - Where the list of posts will be inserted</li>
            </ul>

            <textarea
                name="list_template"
                rows="20"
                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                required
            ><?php echo htmlspecialchars($templates['list_template'] ?? ''); ?></textarea>
        </div>

        <hr class="border-gray-200">

        <!-- Post Item Template -->
        <div>
            <h3 class="text-lg font-medium text-gray-900 mb-4">Post Item Template (for list)</h3>
            <p class="text-sm text-gray-600 mb-2">Template for each post in the blog list. Available placeholders:</p>
            <ul class="text-sm text-gray-600 mb-4 list-disc list-inside">
                <li><code class="bg-gray-100 px-1 py-0.5 rounded">{TITLE}</code> - Post title</li>
                <li><code class="bg-gray-100 px-1 py-0.5 rounded">{DATE}</code> - Post date</li>
                <li><code class="bg-gray-100 px-1 py-0.5 rounded">{EXCERPT}</code> - Post excerpt</li>
                <li><code class="bg-gray-100 px-1 py-0.5 rounded">{SLUG}</code> - Post URL slug</li>
                <li><code class="bg-gray-100 px-1 py-0.5 rounded">{COLLECTION_BASE_PATH}</code> - Collection path (e.g., "blog")</li>
            </ul>

            <textarea
                name="post_item_template"
                rows="10"
                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                required
            ><?php echo htmlspecialchars($templates['post_item_template'] ?? ''); ?></textarea>
        </div>

        <div class="pt-4">
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                Save Templates
            </button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
