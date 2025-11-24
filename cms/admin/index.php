<?php
/**
 * Admin Dashboard
 */

require_once __DIR__ . '/includes/auth-guard.php';
require_once __DIR__ . '/../core/PageManager.php';

$reservedFolders = $config['reserved_folders'] ?? ['cms'];
$pageManager = new PageManager($config['root_dir'], $reservedFolders);
$pages = $pageManager->listPages();

$pageTitle = 'Dashboard';
$activePage = 'dashboard';

require __DIR__ . '/includes/header.php';
?>

<h1 class="text-3xl font-bold text-gray-900 mb-6">Dashboard</h1>

<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-3">Welcome, <?php echo htmlspecialchars($currentUser['username']); ?>!</h2>
    <p class="text-gray-600">This is your MCP CMS admin panel. Use the navigation to manage your pages.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-3">Quick Stats</h2>
        <div class="space-y-2">
            <p class="text-gray-700"><strong class="font-medium">Total Pages:</strong> <span class="text-blue-600"><?php echo count($pages); ?></span></p>
            <p class="text-gray-700"><strong class="font-medium">Site Name:</strong> <?php echo htmlspecialchars($config['site_name'] ?? 'Not set'); ?></p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-3">Quick Actions</h2>
        <div class="flex flex-wrap gap-3">
            <a href="/cms/admin/pages.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">Manage Pages</a>
            <a href="/cms/admin/create.php" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">Create New Page</a>
            <a href="/cms/admin/mcp-config.php" class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition">MCP Config</a>
        </div>
    </div>
</div>

<div class="bg-white rounded-lg shadow-md p-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-4">Recent Pages</h2>
    <?php if (empty($pages)): ?>
        <p class="text-gray-600">No pages found. <a href="/cms/admin/create.php" class="text-blue-600 hover:text-blue-800">Create your first page</a>.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Page ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach (array_slice($pages, 0, 5) as $page): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <code class="text-sm text-gray-900 bg-gray-100 px-2 py-1 rounded"><?php echo htmlspecialchars($page['id'] ?: '/'); ?></code>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <a href="/cms/admin/edit.php?page_id=<?php echo urlencode($page['id']); ?>" class="text-blue-600 hover:text-blue-800 mr-3">Edit</a>
                                <a href="/cms/admin/preview.php?page_id=<?php echo urlencode($page['id']); ?>" target="_blank" class="text-green-600 hover:text-green-800">Preview</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($pages) > 5): ?>
            <p class="mt-4"><a href="/cms/admin/pages.php" class="text-blue-600 hover:text-blue-800">View all pages &rarr;</a></p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
