<?php
/**
 * Admin Blog Posts Listing
 */

require_once __DIR__ . '/includes/auth-guard.php';
require_once __DIR__ . '/../core/BlogManager.php';
require_once __DIR__ . '/../core/BackupManager.php';
require_once __DIR__ . '/../core/SitemapGenerator.php';
require_once __DIR__ . '/../core/CSRF.php';

$reservedFolders = $config['reserved_folders'] ?? ['cms'];
$backupManager = new BackupManager($config['backups_dir'], $config['max_backups_per_page']);
$sitemapGenerator = new SitemapGenerator($config['root_dir'], $config['base_url'] ?? 'http://localhost', $reservedFolders, $config['drafts_dir'] ?? null);
$blogManager = new BlogManager($config['root_dir'], $config['drafts_dir'], $sitemapGenerator, $backupManager);

// Get current collection (default: blog)
$collectionId = $_GET['collection'] ?? 'blog';
$collections = $blogManager->getCollections();

// Handle post actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verifyOrDie();

    $action = $_POST['action'] ?? '';
    $slug = $_POST['slug'] ?? '';
    $collectionId = $_POST['collection'] ?? $collectionId; // Use POST collection if provided

    try {
        switch ($action) {
            case 'publish':
                $blogManager->publishPost($collectionId, $slug);
                $successMessage = "Post published successfully.";
                break;

            case 'unpublish':
                $blogManager->unpublishPost($collectionId, $slug);
                $successMessage = "Post unpublished successfully.";
                break;

            case 'delete':
                $status = $_POST['status'] ?? 'draft';
                $blogManager->deletePost($collectionId, $slug, $status);
                $successMessage = "Post deleted successfully.";
                break;
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Get posts
try {
    $posts = $blogManager->listPosts($collectionId);
} catch (Exception $e) {
    $posts = [];
    $errorMessage = $e->getMessage();
}

$pageTitle = 'Blog Posts';
$activePage = 'blog';

require __DIR__ . '/includes/header.php';
?>

<h1 class="text-3xl font-bold text-gray-900 mb-6">Blog Posts</h1>

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

<!-- Collection selector -->
<?php if (count($collections) > 1): ?>
<div class="mb-6">
    <label class="block text-sm font-medium text-gray-700 mb-2">Collection:</label>
    <select onchange="window.location.href='?collection=' + this.value" class="px-4 py-2 border border-gray-300 rounded-md">
        <?php foreach ($collections as $coll): ?>
            <option value="<?php echo htmlspecialchars($coll['id']); ?>" <?php echo $coll['id'] === $collectionId ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($coll['label']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow-md p-6">
    <div class="mb-4">
        <a href="/cms/admin/blog-edit.php?collection=<?php echo urlencode($collectionId); ?>" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">Create New Post</a>
    </div>

    <?php if (empty($posts)): ?>
        <p class="text-gray-600">No posts found in this collection.</p>
    <?php else: ?>
        <!-- Separate drafts and published -->
        <?php
        $drafts = array_filter($posts, fn($p) => $p['status'] === 'draft');
        $published = array_filter($posts, fn($p) => $p['status'] === 'published');
        ?>

        <!-- Drafts -->
        <?php if (!empty($drafts)): ?>
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Drafts</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slug</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($drafts as $post): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <code class="text-sm text-gray-900 bg-gray-100 px-2 py-1 rounded"><?php echo htmlspecialchars($post['slug']); ?></code>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Draft</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <a href="/cms/admin/blog-edit.php?collection=<?php echo urlencode($collectionId); ?>&slug=<?php echo urlencode($post['slug']); ?>&status=draft" class="text-blue-600 hover:text-blue-800 mr-3">Edit</a>

                                    <a href="/cms/admin/blog-preview.php?collection=<?php echo urlencode($collectionId); ?>&slug=<?php echo urlencode($post['slug']); ?>&draft=1" target="_blank" class="text-orange-600 hover:text-orange-800 mr-3">Preview Draft</a>

                                    <form method="post" class="inline" onsubmit="return confirm('Publish this post?');">
                                        <?php echo CSRF::inputField(); ?>
                                        <input type="hidden" name="action" value="publish">
                                        <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionId); ?>">
                                        <input type="hidden" name="slug" value="<?php echo htmlspecialchars($post['slug']); ?>">
                                        <button type="submit" class="text-green-600 hover:text-green-800 mr-3">Publish</button>
                                    </form>

                                    <form method="post" class="inline" onsubmit="return confirm('Delete this draft?');">
                                        <?php echo CSRF::inputField(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionId); ?>">
                                        <input type="hidden" name="slug" value="<?php echo htmlspecialchars($post['slug']); ?>">
                                        <input type="hidden" name="status" value="draft">
                                        <button type="submit" class="text-red-600 hover:text-red-800">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Published -->
        <?php if (!empty($published)): ?>
        <div>
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Published</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slug</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($published as $post): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <code class="text-sm text-gray-900 bg-gray-100 px-2 py-1 rounded"><?php echo htmlspecialchars($post['slug']); ?></code>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Published</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <a href="/cms/admin/blog-edit.php?collection=<?php echo urlencode($collectionId); ?>&slug=<?php echo urlencode($post['slug']); ?>&status=published" class="text-blue-600 hover:text-blue-800 mr-3">Edit</a>

                                    <a href="/cms/admin/blog-preview.php?collection=<?php echo urlencode($collectionId); ?>&slug=<?php echo urlencode($post['slug']); ?>" target="_blank" class="text-green-600 hover:text-green-800 mr-3">Preview Live</a>

                                    <form method="post" class="inline" onsubmit="return confirm('Unpublish this post?');">
                                        <?php echo CSRF::inputField(); ?>
                                        <input type="hidden" name="action" value="unpublish">
                                        <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionId); ?>">
                                        <input type="hidden" name="slug" value="<?php echo htmlspecialchars($post['slug']); ?>">
                                        <button type="submit" class="text-orange-600 hover:text-orange-800 mr-3">Unpublish</button>
                                    </form>

                                    <form method="post" class="inline" onsubmit="return confirm('Delete this post? This cannot be undone!');">
                                        <?php echo CSRF::inputField(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionId); ?>">
                                        <input type="hidden" name="slug" value="<?php echo htmlspecialchars($post['slug']); ?>">
                                        <input type="hidden" name="status" value="published">
                                        <button type="submit" class="text-red-600 hover:text-red-800">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
