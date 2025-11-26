<?php
/**
 * Admin Pages Listing
 */

require_once __DIR__ . '/includes/auth-guard.php';
require_once __DIR__ . '/../core/PageManager.php';
require_once __DIR__ . '/../core/BackupManager.php';
require_once __DIR__ . '/../core/SitemapGenerator.php';
require_once __DIR__ . '/../core/PageSettings.php';
require_once __DIR__ . '/../core/CSRF.php';

$reservedFolders = $config['reserved_folders'] ?? ['cms'];
$backupManager = new BackupManager($config['backups_dir'], $config['max_backups_per_page']);
$sitemapGenerator = new SitemapGenerator($config['root_dir'], $config['base_url'] ?? 'http://localhost', $reservedFolders, $config['drafts_dir'] ?? null);
$pageSettings = new PageSettings($config['cms_dir'] . '/settings');
$pageManager = new PageManager($config['root_dir'], $reservedFolders, $config['drafts_dir'] ?? null, $backupManager, $sitemapGenerator, $pageSettings);

// Handle page actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verifyOrDie();

    $action = $_POST['action'] ?? '';
    $pageId = $_POST['page_id'] ?? '';

    try {
        switch ($action) {
            case 'delete':
                $pageManager->deletePage($pageId);
                $successMessage = "Page deleted successfully.";
                break;

            case 'publish':
                $pageManager->publishDraft($pageId);
                $successMessage = "Draft published successfully.";
                break;

            case 'discard':
                $pageManager->discardDraft($pageId);
                $successMessage = "Draft discarded successfully.";
                break;
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

$pages = $pageManager->listPages();

$pageTitle = 'Pages';
$activePage = 'pages';

require __DIR__ . '/includes/header.php';
?>

<h1 class="text-3xl font-bold text-gray-900 mb-6">All Pages</h1>

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
    <div class="mb-4">
        <a href="/cms/admin/create.php" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">Create New Page</a>
    </div>

    <?php if (empty($pages)): ?>
        <p class="text-gray-600">No pages found.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Page ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($pages as $page): ?>
                        <?php $hasDraft = $pageManager->hasDraft($page['id']); ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <code class="text-sm text-gray-900 bg-gray-100 px-2 py-1 rounded"><?php echo htmlspecialchars($page['id'] ?: '/'); ?></code>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($hasDraft): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Has Draft</span>
                                <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Live</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <a href="/cms/admin/edit.php?page_id=<?php echo urlencode($page['id']); ?>" class="text-blue-600 hover:text-blue-800 mr-3">Edit</a>

                                <!-- Always show Preview Live -->
                                <a href="/cms/admin/preview.php?page_id=<?php echo urlencode($page['id']); ?>" target="_blank" class="text-green-600 hover:text-green-800 mr-3">Preview Live</a>

                                <?php if ($hasDraft): ?>
                                    <!-- Show draft-specific actions -->
                                    <a href="/cms/admin/preview.php?page_id=<?php echo urlencode($page['id']); ?>&draft=1" target="_blank" class="text-orange-600 hover:text-orange-800 mr-3">Preview Draft</a>

                                    <form method="post" class="inline" onsubmit="return confirm('Publish this draft?');">
                                        <?php echo CSRF::inputField(); ?>
                                        <input type="hidden" name="action" value="publish">
                                        <input type="hidden" name="page_id" value="<?php echo htmlspecialchars($page['id']); ?>">
                                        <button type="submit" class="text-green-600 hover:text-green-800 mr-3">Publish</button>
                                    </form>

                                    <form method="post" class="inline" onsubmit="return confirm('Discard this draft?');">
                                        <?php echo CSRF::inputField(); ?>
                                        <input type="hidden" name="action" value="discard">
                                        <input type="hidden" name="page_id" value="<?php echo htmlspecialchars($page['id']); ?>">
                                        <button type="submit" class="text-orange-600 hover:text-orange-800 mr-3">Discard</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($page['id'] !== ''): ?>
                                    <form method="post" class="inline" onsubmit="return confirm('Are you sure you want to delete this page?');">
                                        <?php echo CSRF::inputField(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="page_id" value="<?php echo htmlspecialchars($page['id']); ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-800">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
