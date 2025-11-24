<?php
/**
 * Admin Page Creation (via duplication)
 */

require_once __DIR__ . '/includes/auth-guard.php';
require_once __DIR__ . '/../core/PageManager.php';
require_once __DIR__ . '/../core/CSRF.php';

$pageManager = new PageManager($config['root_dir']);
$pages = $pageManager->listPages();

// Handle page creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verifyOrDie();

    $sourcePageId = $_POST['source_page_id'] ?? '';
    $newPageId = trim($_POST['new_page_id'] ?? '', '/');

    try {
        if (!$newPageId) {
            throw new Exception('New page ID is required');
        }

        // Validate page ID format (alphanumeric, hyphens, slashes)
        if (!preg_match('/^[a-z0-9\-\/]+$/i', $newPageId)) {
            throw new Exception('Invalid page ID format. Use only letters, numbers, hyphens, and slashes.');
        }

        // Duplicate the page
        $pageManager->duplicatePage($sourcePageId, $newPageId);

        // Redirect to edit the new page
        header('Location: /cms/admin/edit.php?page_id=' . urlencode($newPageId));
        exit;

    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Create New Page';
$activePage = 'create';

require __DIR__ . '/includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900 mb-2">Create New Page</h1>
    <p class="text-gray-600">
        <a href="/cms/admin/pages.php" class="text-blue-600 hover:text-blue-800">&larr; Back to Pages</a>
    </p>
</div>

<?php if (isset($errorMessage)): ?>
    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
        <p class="text-red-700"><?php echo htmlspecialchars($errorMessage); ?></p>
    </div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow-md p-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-4">Duplicate Existing Page</h2>

    <p class="text-gray-600 mb-6">Create a new page by duplicating an existing one. You can then edit the blocks in the new page.</p>

    <form method="post" class="space-y-6">
        <?php echo CSRF::inputField(); ?>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Source Page:
            </label>
            <select name="source_page_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">-- Select a page to duplicate --</option>
                <?php foreach ($pages as $page): ?>
                    <option value="<?php echo htmlspecialchars($page['id']); ?>">
                        <?php echo htmlspecialchars($page['id'] ?: '/ (Home)'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                New Page ID:
            </label>
            <input type="text" name="new_page_id" placeholder="e.g., about, services/web-design" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            <p class="mt-1 text-sm text-gray-500">Use lowercase letters, numbers, hyphens, and slashes for nested pages. Example: <code class="bg-gray-100 px-1 py-0.5 rounded">about</code> or <code class="bg-gray-100 px-1 py-0.5 rounded">services/web-design</code></p>
        </div>

        <div>
            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">Create Page</button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
