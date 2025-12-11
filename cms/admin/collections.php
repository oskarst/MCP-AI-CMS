<?php
/**
 * Collections Management Page
 */

require_once __DIR__ . '/includes/auth-guard.php';
require_once __DIR__ . '/../core/BlogManager.php';
require_once __DIR__ . '/../core/SitemapGenerator.php';
require_once __DIR__ . '/../core/BackupManager.php';
require_once __DIR__ . '/../core/CollectionIndexGenerator.php';
require_once __DIR__ . '/../core/CSRF.php';

$backupManager = new BackupManager($config['backups_dir'], $config['max_backups_per_page']);
$sitemapGenerator = new SitemapGenerator($config['root_dir'], $config['base_url'] ?? 'http://localhost', $config['reserved_folders'] ?? ['cms'], $config['drafts_dir'] ?? null);
$blogManager = new BlogManager($config['root_dir'], $config['drafts_dir'], $sitemapGenerator, $backupManager);
$indexGenerator = new CollectionIndexGenerator($config['root_dir'], __DIR__ . '/../config/blog-templates.json');

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verifyOrDie();

    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'create':
                $label = trim($_POST['label'] ?? '');
                $id = trim($_POST['id'] ?? '');
                $basePath = trim($_POST['base_path'] ?? '');
                $indexType = $_POST['index_type'] ?? 'auto';

                if (empty($label) || empty($id) || empty($basePath)) {
                    throw new Exception("All fields are required");
                }

                $blogManager->createCollection($id, $label, $basePath, $indexType);

                // Generate initial index page
                $collection = $blogManager->getCollection($id);
                $posts = $blogManager->listPosts($id);
                $indexGenerator->generateIndex($collection, $posts);

                $successMessage = "Collection '{$label}' created successfully.";
                break;

            case 'update':
                $id = $_POST['id'] ?? '';
                $settings = [
                    'label' => trim($_POST['label'] ?? ''),
                    'base_path' => trim($_POST['base_path'] ?? ''),
                    'index_type' => $_POST['index_type'] ?? 'auto',
                    'posts_per_page' => (int)($_POST['posts_per_page'] ?? 10),
                    'sort_by' => $_POST['sort_by'] ?? 'date',
                    'sort_order' => $_POST['sort_order'] ?? 'desc',
                    'show_excerpts' => isset($_POST['show_excerpts']),
                ];

                if (empty($settings['label']) || empty($id) || empty($settings['base_path'])) {
                    throw new Exception("All required fields must be filled");
                }

                $blogManager->updateCollection($id, $settings);

                // Regenerate index page
                $collection = $blogManager->getCollection($id);
                $posts = $blogManager->listPosts($id);
                $indexGenerator->generateIndex($collection, $posts);

                $successMessage = "Collection '{$settings['label']}' updated successfully.";
                break;

            case 'delete':
                $id = $_POST['id'] ?? '';
                $collectionToDelete = $blogManager->getCollection($id);

                $blogManager->deleteCollection($id);

                // Remove index page
                $indexPath = $config['root_dir'] . '/' . $collectionToDelete['base_path'] . '/index.php';
                if (file_exists($indexPath)) {
                    @unlink($indexPath);
                }

                $successMessage = "Collection deleted successfully.";
                break;

            case 'regenerate_index':
                $id = $_POST['id'] ?? '';
                $collection = $blogManager->getCollection($id);

                if (!$collection) {
                    throw new Exception("Collection not found");
                }

                $posts = $blogManager->listPosts($id);
                $indexGenerator->generateIndex($collection, $posts);

                $successMessage = "Index page regenerated for '{$collection['label']}'.";
                break;
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

$collections = $blogManager->getCollections();

// Add post counts to collections
foreach ($collections as &$collection) {
    $collection['post_count'] = $blogManager->getPostCount($collection['id']);
}
unset($collection); // Important: unset reference to avoid bugs in subsequent foreach loops

$pageTitle = 'Manage Collections';
$activePage = 'collections';

require __DIR__ . '/includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900 mb-2">Manage Collections</h1>
    <p class="text-gray-600">Organize your content into collections like Blog, News, Events, etc.</p>
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

<!-- Add New Collection -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6" x-data="{ open: false }">
    <button
        type="button"
        @click="open = !open"
        class="w-full flex items-center justify-between text-left">
        <h2 class="text-xl font-semibold text-gray-900">Add New Collection</h2>
        <svg
            class="w-5 h-5 text-gray-500 transition-transform"
            :class="open ? 'rotate-180' : ''"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>

    <div x-show="open" x-cloak class="mt-4 pt-4 border-t border-gray-200">
        <form method="post" class="space-y-4">
            <?php echo CSRF::inputField(); ?>
            <input type="hidden" name="action" value="create">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Collection Label <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    name="label"
                    required
                    placeholder="e.g., News, Events, Portfolio"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    x-ref="label"
                    @input="$refs.id.value = $refs.label.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''); $refs.basePath.value = $refs.id.value">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Collection ID <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    name="id"
                    required
                    pattern="[a-z0-9-]+"
                    placeholder="e.g., news, events, portfolio"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono"
                    x-ref="id">
                <p class="text-xs text-gray-500 mt-1">Lowercase letters, numbers, and hyphens only</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Base Path <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    name="base_path"
                    required
                    placeholder="e.g., news, events, portfolio"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono"
                    x-ref="basePath">
                <p class="text-xs text-gray-500 mt-1">URL path (e.g., "news" → /news/)</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Index Page Type
                </label>
                <select name="index_type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="auto">Auto-generated (faster, regenerated on changes)</option>
                    <option value="dynamic">Dynamic (always current, slightly slower)</option>
                </select>
            </div>

            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                Create Collection
            </button>
        </form>
    </div>
</div>

<!-- Existing Collections -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-900">Existing Collections</h2>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Label</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Base Path</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Posts</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Index Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($collections as $collection): ?>
                <tr x-data="{ editing: false }">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span x-show="!editing" class="text-sm font-medium text-gray-900">
                            <?php echo htmlspecialchars($collection['label']); ?>
                        </span>
                        <input
                            x-show="editing"
                            x-cloak
                            type="text"
                            value="<?php echo htmlspecialchars($collection['label']); ?>"
                            x-ref="editLabel_<?php echo htmlspecialchars($collection['id']); ?>"
                            class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <code class="text-sm text-gray-600"><?php echo htmlspecialchars($collection['id']); ?></code>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span x-show="!editing" class="text-sm text-gray-600">
                            /<?php echo htmlspecialchars($collection['base_path']); ?>/
                        </span>
                        <input
                            x-show="editing"
                            x-cloak
                            type="text"
                            value="<?php echo htmlspecialchars($collection['base_path']); ?>"
                            x-ref="editBasePath_<?php echo htmlspecialchars($collection['id']); ?>"
                            class="w-full px-2 py-1 border border-gray-300 rounded text-sm font-mono">
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                        <?php echo $collection['post_count']; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span x-show="!editing" class="text-sm text-gray-600">
                            <?php echo htmlspecialchars($collection['index_type'] ?? 'auto'); ?>
                        </span>
                        <select
                            x-show="editing"
                            x-cloak
                            x-ref="editIndexType_<?php echo htmlspecialchars($collection['id']); ?>"
                            class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                            <option value="auto" <?php echo ($collection['index_type'] ?? 'auto') === 'auto' ? 'selected' : ''; ?>>Auto</option>
                            <option value="dynamic" <?php echo ($collection['index_type'] ?? 'auto') === 'dynamic' ? 'selected' : ''; ?>>Dynamic</option>
                        </select>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm space-x-2">
                        <!-- View Collection -->
                        <a
                            href="/<?php echo htmlspecialchars($collection['base_path']); ?>/"
                            target="_blank"
                            class="text-blue-600 hover:text-blue-800"
                            title="View collection index page">
                            View
                        </a>

                        <!-- Settings Button -->
                        <button
                            x-show="!editing"
                            @click="$dispatch('open-settings-<?php echo htmlspecialchars($collection['id']); ?>')"
                            class="text-purple-600 hover:text-purple-800">
                            Settings
                        </button>

                        <!-- Regenerate Index -->
                        <form method="post" class="inline">
                            <?php echo CSRF::inputField(); ?>
                            <input type="hidden" name="action" value="regenerate_index">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($collection['id']); ?>">
                            <button type="submit" class="text-purple-600 hover:text-purple-800">
                                Regenerate Index
                            </button>
                        </form>

                        <!-- Delete -->
                        <form method="post" class="inline" onsubmit="return confirm('Delete this collection? This cannot be undone.');">
                            <?php echo CSRF::inputField(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($collection['id']); ?>">
                            <button type="submit" class="text-red-600 hover:text-red-800">
                                Delete
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Settings Modals for Each Collection -->
<?php foreach ($collections as $collection): ?>
<div
    x-data="{ open: false }"
    @open-settings-<?php echo htmlspecialchars($collection['id']); ?>.window="open = true"
    x-show="open"
    x-cloak
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    @click.self="open = false">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">
                Settings: <?php echo htmlspecialchars($collection['label']); ?>
            </h3>
            <button @click="open = false" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form method="post" class="p-6 space-y-4">
            <?php echo CSRF::inputField(); ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($collection['id']); ?>">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Collection Label
                </label>
                <input
                    type="text"
                    name="label"
                    value="<?php echo htmlspecialchars($collection['label']); ?>"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Base Path
                </label>
                <input
                    type="text"
                    name="base_path"
                    value="<?php echo htmlspecialchars($collection['base_path']); ?>"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Index Page Type
                </label>
                <select name="index_type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="auto" <?php echo ($collection['index_type'] ?? 'auto') === 'auto' ? 'selected' : ''; ?>>Auto-generated</option>
                    <option value="dynamic" <?php echo ($collection['index_type'] ?? 'auto') === 'dynamic' ? 'selected' : ''; ?>>Dynamic</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Posts Per Page
                </label>
                <input
                    type="number"
                    name="posts_per_page"
                    value="<?php echo htmlspecialchars($collection['posts_per_page'] ?? 10); ?>"
                    min="1"
                    max="100"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-500 mt-1">Maximum number of posts to show per page (1-100)</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Sort By
                </label>
                <select name="sort_by" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="date" <?php echo ($collection['sort_by'] ?? 'date') === 'date' ? 'selected' : ''; ?>>Date</option>
                    <option value="title" <?php echo ($collection['sort_by'] ?? 'date') === 'title' ? 'selected' : ''; ?>>Title</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Sort Order
                </label>
                <select name="sort_order" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="desc" <?php echo ($collection['sort_order'] ?? 'desc') === 'desc' ? 'selected' : ''; ?>>Descending (Newest First)</option>
                    <option value="asc" <?php echo ($collection['sort_order'] ?? 'desc') === 'asc' ? 'selected' : ''; ?>>Ascending (Oldest First)</option>
                </select>
            </div>

            <div>
                <label class="flex items-center">
                    <input
                        type="checkbox"
                        name="show_excerpts"
                        <?php echo ($collection['show_excerpts'] ?? true) ? 'checked' : ''; ?>
                        class="mr-2 h-4 w-4 text-blue-600 rounded">
                    <span class="text-sm text-gray-700">Show post excerpts in index page</span>
                </label>
            </div>

            <div class="pt-4 border-t border-gray-200 flex gap-3">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                    Save Settings
                </button>
                <button type="button" @click="open = false" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
