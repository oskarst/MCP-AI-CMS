<?php
/**
 * Blog Post Block Sync Tool
 *
 * Allows syncing non-custom blocks across multiple blog posts
 * Completely separate from page block syncing
 */

require_once __DIR__ . '/includes/auth-guard.php';
require_once __DIR__ . '/../core/BlogManager.php';
require_once __DIR__ . '/../core/BlockParser.php';
require_once __DIR__ . '/../core/BackupManager.php';
require_once __DIR__ . '/../core/CSRF.php';

$blogManager = new BlogManager($config['root_dir'], $config['drafts_dir']);
$blockParser = new BlockParser();
$backupManager = new BackupManager($config['backups_dir'], $config['max_backups_per_page']);

$collections = $blogManager->getCollections();
$collectionId = $_GET['collection'] ?? 'blog';

// Handle sync operation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sync') {
    CSRF::verifyOrDie();

    $sourceSlug = $_POST['source_slug'] ?? '';
    $sourceStatus = $_POST['source_status'] ?? 'published';
    $blockName = $_POST['block_name'] ?? '';
    $targetPosts = $_POST['target_posts'] ?? [];

    try {
        if (!$blockName) {
            throw new Exception('Block name is required');
        }

        // Get source block content
        $sourcePath = $blogManager->getPostPath($collectionId, $sourceSlug, $sourceStatus);
        if (!$sourcePath) {
            throw new Exception('Source post not found');
        }

        $sourceBlocks = $blockParser->parseBlocks($sourcePath);
        $sourceBlock = null;

        foreach ($sourceBlocks as $block) {
            if ($block['name'] === $blockName) {
                $sourceBlock = $block;
                break;
            }
        }

        if (!$sourceBlock) {
            throw new Exception('Source block not found');
        }

        // Sync to target posts
        $syncedCount = 0;
        $skippedCount = 0;

        foreach ($targetPosts as $targetData) {
            list($targetSlug, $targetStatus) = explode(':', $targetData);

            if ($targetSlug === $sourceSlug && $targetStatus === $sourceStatus) {
                continue; // Skip source post
            }

            $targetPath = $blogManager->getPostPath($collectionId, $targetSlug, $targetStatus);
            if (!$targetPath) {
                continue;
            }

            // Parse target post blocks
            $targetBlocks = $blockParser->parseBlocks($targetPath);
            $targetBlock = null;

            foreach ($targetBlocks as $block) {
                if ($block['name'] === $blockName) {
                    $targetBlock = $block;
                    break;
                }
            }

            if (!$targetBlock) {
                continue; // Block doesn't exist in target
            }

            if ($targetBlock['custom']) {
                $skippedCount++; // Skip custom blocks
                continue;
            }

            // Create backup
            $backupId = $collectionId . '/' . $targetSlug;
            $backupManager->createBackup($backupId, $targetPath);

            // Update the block
            $blockParser->updateBlock($targetPath, $blockName, $sourceBlock['content'], false);
            $syncedCount++;
        }

        $successMessage = "Synced to {$syncedCount} post(s). Skipped {$skippedCount} custom block(s).";

    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Get all posts for current collection
$posts = $blogManager->listPosts($collectionId);

$pageTitle = 'Sync Post Blocks';
$activePage = 'blog-sync';

require __DIR__ . '/includes/header.php';
?>

<h1 class="text-3xl font-bold text-gray-900 mb-6">Sync Post Blocks</h1>

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
        <h2 class="text-lg font-semibold text-gray-900 mb-2">How It Works</h2>
        <p class="text-gray-600 text-sm">
            This tool syncs a block from one post to multiple other posts. Blocks marked as "custom" will be skipped.
            This is useful for updating shared content like headers or footers across all posts.
        </p>
        <p class="text-gray-600 text-sm mt-2">
            <strong>Note:</strong> Post blocks are completely separate from page blocks. Syncing post blocks will not affect pages.
        </p>
    </div>

    <?php if (empty($posts)): ?>
        <p class="text-gray-600">No posts found in this collection.</p>
    <?php else: ?>
        <form method="post" class="space-y-6">
            <?php echo CSRF::inputField(); ?>
            <input type="hidden" name="action" value="sync">

            <!-- Source Post Selection -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Source Post:</label>
                <select name="source_slug" id="sourcePost" required class="w-full px-4 py-2 border border-gray-300 rounded-md" onchange="loadSourceBlocks()">
                    <option value="">Select a source post...</option>
                    <?php foreach ($posts as $post): ?>
                        <option value="<?php echo htmlspecialchars($post['slug']); ?>" data-status="<?php echo htmlspecialchars($post['status']); ?>">
                            <?php echo htmlspecialchars($post['slug']); ?> (<?php echo htmlspecialchars($post['status']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="source_status" id="sourceStatus">
            </div>

            <!-- Block Selection -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Block to Sync:</label>
                <input type="text" name="block_name" required placeholder="e.g., header, footer, sidebar" class="w-full px-4 py-2 border border-gray-300 rounded-md">
                <p class="mt-1 text-sm text-gray-500">Enter the block name (e.g., "header", "footer", "content")</p>
            </div>

            <!-- Target Posts Selection -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Target Posts:</label>
                <div class="border border-gray-300 rounded-md p-4 max-h-64 overflow-y-auto">
                    <?php foreach ($posts as $post): ?>
                        <label class="flex items-center py-2">
                            <input type="checkbox" name="target_posts[]" value="<?php echo htmlspecialchars($post['slug'] . ':' . $post['status']); ?>" class="mr-2">
                            <span class="text-sm"><?php echo htmlspecialchars($post['slug']); ?>
                                <span class="text-xs <?php echo $post['status'] === 'draft' ? 'text-yellow-600' : 'text-green-600'; ?>">
                                    (<?php echo htmlspecialchars($post['status']); ?>)
                                </span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="mt-1 text-sm text-gray-500">Select which posts to sync this block to. Custom blocks will be skipped automatically.</p>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                    Sync Block
                </button>
                <a href="/cms/admin/blog.php?collection=<?php echo urlencode($collectionId); ?>" class="px-6 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition inline-block">
                    Back to Posts
                </a>
            </div>
        </form>

        <script>
        function loadSourceBlocks() {
            const select = document.getElementById('sourcePost');
            const option = select.options[select.selectedIndex];
            const status = option.getAttribute('data-status');
            document.getElementById('sourceStatus').value = status;
        }
        </script>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
