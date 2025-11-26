<?php
/**
 * Admin Blog Post Editor
 */

require_once __DIR__ . '/includes/auth-guard.php';
require_once __DIR__ . '/../core/BlogManager.php';
require_once __DIR__ . '/../core/BlockParser.php';
require_once __DIR__ . '/../core/BackupManager.php';
require_once __DIR__ . '/../core/SitemapGenerator.php';
require_once __DIR__ . '/../core/CSRF.php';

$reservedFolders = $config['reserved_folders'] ?? ['cms'];
$backupManager = new BackupManager($config['backups_dir'], $config['max_backups_per_page']);
$sitemapGenerator = new SitemapGenerator($config['root_dir'], $config['base_url'] ?? 'http://localhost', $reservedFolders, $config['drafts_dir'] ?? null);
$blogManager = new BlogManager($config['root_dir'], $config['drafts_dir'], $sitemapGenerator, $backupManager);
$blockParser = new BlockParser();

$collectionId = $_GET['collection'] ?? 'blog';
$slug = $_GET['slug'] ?? '';
$status = $_GET['status'] ?? 'draft';
$isNew = empty($slug);

// Handle new post creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    CSRF::verifyOrDie();

    $action = $_POST['action'];

    if ($action === 'create') {
        $newSlug = $_POST['slug'] ?? '';
        if (!empty($newSlug)) {
            try {
                $blogManager->createPost($collectionId, $newSlug);
                header('Location: /cms/admin/blog-edit.php?collection=' . urlencode($collectionId) . '&slug=' . urlencode($newSlug) . '&status=draft');
                exit;
            } catch (Exception $e) {
                $errorMessage = $e->getMessage();
            }
        }
    } elseif ($action === 'save') {
        // Save block content - ALWAYS save to draft
        try {
            // Get draft path (or published if no draft exists yet)
            $draftPath = $blogManager->getPostPath($collectionId, $slug, 'draft');
            $publishedPath = $blogManager->getPostPath($collectionId, $slug, 'published');

            // If editing a published post and no draft exists, copy published to draft first
            if ($status === 'published' && !$draftPath && $publishedPath) {
                $collection = $blogManager->getCollection($collectionId);
                $draftDir = $config['drafts_dir'] . '/' . $collectionId . '/' . $slug;

                // Create draft directory
                if (!is_dir($draftDir)) {
                    mkdir($draftDir, 0755, true);
                }

                // Copy published content to draft
                copy($publishedPath, $draftDir . '/index.php');
                $draftPath = $draftDir . '/index.php';
            }

            // Get the path to edit (draft if exists, otherwise use current)
            $editPath = $draftPath ?: $blogManager->getPostPath($collectionId, $slug, $status);

            if ($editPath) {
                // Update each block
                foreach ($_POST as $key => $value) {
                    if (strpos($key, 'block_') === 0) {
                        $blockName = substr($key, 6); // Remove 'block_' prefix
                        $customFlag = isset($_POST['custom_' . $blockName]) ? true : null;
                        $blockParser->updateBlock($editPath, $blockName, $value, $customFlag);
                    }
                }

                // If we were editing a published post, redirect to draft view
                if ($status === 'published') {
                    header('Location: /cms/admin/blog-edit.php?collection=' . urlencode($collectionId) . '&slug=' . urlencode($slug) . '&status=draft');
                    exit;
                }

                $successMessage = 'Post saved as draft.';
            }
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
        }
    } elseif ($action === 'publish') {
        // Publish draft post
        try {
            // Check if draft exists
            $draftPath = $blogManager->getPostPath($collectionId, $slug, 'draft');
            if ($draftPath) {
                $blogManager->publishPost($collectionId, $slug);
                $successMessage = 'Post published successfully.';
                // Redirect to published version
                header('Location: /cms/admin/blog-edit.php?collection=' . urlencode($collectionId) . '&slug=' . urlencode($slug) . '&status=published');
                exit;
            } else {
                throw new Exception('No draft to publish');
            }
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
        }
    }
}

// Get post data - always prefer draft if it exists
$postPath = null;
$blocks = [];
$hasDraft = false;
$actualStatus = $status;

if (!$isNew) {
    // Check if draft exists
    $draftPath = $blogManager->getPostPath($collectionId, $slug, 'draft');
    $publishedPath = $blogManager->getPostPath($collectionId, $slug, 'published');

    if ($draftPath) {
        // Draft exists, use it for editing
        $postPath = $draftPath;
        $hasDraft = true;
        $actualStatus = 'draft';
    } elseif ($publishedPath) {
        // No draft, use published
        $postPath = $publishedPath;
        $actualStatus = 'published';
    } else {
        $errorMessage = 'Post not found.';
    }

    if ($postPath) {
        $blocks = $blockParser->parseBlocks($postPath);
    }
}

$pageTitle = $isNew ? 'Create New Post' : 'Edit Post: ' . htmlspecialchars($slug);
$activePage = 'blog';

require __DIR__ . '/includes/header.php';
?>

<!-- CodeMirror CSS and JS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/material-darker.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/php/php.min.js"></script>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900 mb-2">
        <?php echo $isNew ? 'Create New Post' : 'Edit Post'; ?>
        <?php if (!$isNew && $hasDraft): ?>
            <span class="ml-3 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Has Draft</span>
        <?php elseif (!$isNew && $actualStatus === 'published'): ?>
            <span class="ml-3 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Published</span>
        <?php endif; ?>
    </h1>

    <?php if (!$isNew): ?>
        <div class="flex items-center gap-3 text-sm">
            <a href="/cms/admin/blog.php?collection=<?php echo urlencode($collectionId); ?>" class="text-blue-600 hover:text-blue-800">&larr; Back to Blog Posts</a>

            <!-- Always show preview live if published version exists -->
            <?php if ($publishedPath): ?>
                <span class="text-gray-400">|</span>
                <a href="/cms/admin/blog-preview.php?collection=<?php echo urlencode($collectionId); ?>&slug=<?php echo urlencode($slug); ?>" target="_blank" class="text-green-600 hover:text-green-800">Preview Live</a>
            <?php endif; ?>

            <!-- Show draft preview and publish if draft exists -->
            <?php if ($hasDraft): ?>
                <span class="text-gray-400">|</span>
                <a href="/cms/admin/blog-preview.php?collection=<?php echo urlencode($collectionId); ?>&slug=<?php echo urlencode($slug); ?>&draft=1" target="_blank" class="text-orange-600 hover:text-orange-800">Preview Draft</a>
                <span class="text-gray-400">|</span>
                <form method="post" class="inline" onsubmit="return confirm('Publish this post?');">
                    <?php echo CSRF::inputField(); ?>
                    <input type="hidden" name="action" value="publish">
                    <button type="submit" class="text-green-600 hover:text-green-800 font-medium">Publish</button>
                </form>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p class="text-gray-600">
            <a href="/cms/admin/blog.php?collection=<?php echo urlencode($collectionId); ?>" class="text-blue-600 hover:text-blue-800">&larr; Back to Blog Posts</a>
        </p>
    <?php endif; ?>
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

<?php if ($isNew): ?>
    <!-- New post form -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <form method="post">
            <?php echo CSRF::inputField(); ?>
            <input type="hidden" name="action" value="create">

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Post Slug:</label>
                <input type="text" name="slug" required placeholder="my-first-post"
                       class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                <p class="text-sm text-gray-500 mt-1">This will be the URL: /<?php echo htmlspecialchars($collectionId); ?>/<strong>slug</strong>/</p>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">
                    Create Post
                </button>
                <a href="/cms/admin/blog.php?collection=<?php echo urlencode($collectionId); ?>" class="px-6 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition inline-block">
                    Cancel
                </a>
            </div>
        </form>
    </div>
<?php else: ?>
    <!-- Edit post blocks -->
    <form method="post">
        <?php echo CSRF::inputField(); ?>
        <input type="hidden" name="action" value="save">

        <div class="mb-6 bg-gray-50 border border-gray-200 rounded-lg p-4">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm font-medium text-gray-700">Slug: <code class="bg-white px-2 py-1 rounded"><?php echo htmlspecialchars($slug); ?></code></p>
                    <p class="text-sm text-gray-500">Status: <strong><?php echo ucfirst($status); ?></strong></p>
                </div>
                <?php if ($status === 'published'): ?>
                    <a href="/<?php echo htmlspecialchars($collectionId); ?>/<?php echo htmlspecialchars($slug); ?>/" target="_blank" class="text-blue-600 hover:text-blue-800">View Live →</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($blocks)): ?>
            <div class="bg-white rounded-lg shadow-md p-6">
                <p class="text-gray-600">No blocks found in this post.</p>
            </div>
        <?php else: ?>
            <?php foreach ($blocks as $index => $block): ?>
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="mb-4">
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-lg font-semibold text-gray-900">
                                <?php echo htmlspecialchars($block['name']); ?>
                            </label>
                            <div class="flex items-center gap-4">
                                <?php if ($block['role']): ?>
                                    <span class="text-sm px-3 py-1 bg-blue-100 text-blue-800 rounded-full">
                                        role: <?php echo htmlspecialchars($block['role']); ?>
                                    </span>
                                <?php endif; ?>
                                <label class="flex items-center text-sm text-gray-700">
                                    <input type="checkbox" name="custom_<?php echo htmlspecialchars($block['name']); ?>"
                                           <?php echo $block['custom'] ? 'checked' : ''; ?>
                                           class="mr-2">
                                    Custom
                                </label>
                            </div>
                        </div>

                        <textarea
                            name="block_<?php echo htmlspecialchars($block['name']); ?>"
                            rows="<?php echo max(5, min(25, substr_count($block['content'], "\n") + 2)); ?>"
                            class="w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                            spellcheck="false"
                        ><?php echo htmlspecialchars($block['content']); ?></textarea>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex gap-3">
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                        Save Changes
                    </button>
                    <a href="/cms/admin/blog.php?collection=<?php echo urlencode($collectionId); ?>" class="px-6 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition inline-block">
                        Cancel
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </form>
<?php endif; ?>

<?php if (!$isNew && !empty($blocks)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize CodeMirror for all block content textareas
    document.querySelectorAll('textarea[name^="block_"]').forEach(function(textarea) {
        var editor = CodeMirror.fromTextArea(textarea, {
            mode: 'application/x-httpd-php',
            theme: 'material-darker',
            lineNumbers: true,
            lineWrapping: true,
            indentUnit: 4,
            indentWithTabs: false,
            matchBrackets: true,
            autoCloseTags: true,
            viewportMargin: Infinity
        });

        // Update textarea when form submits
        textarea.closest('form').addEventListener('submit', function() {
            editor.save();
        });
    });
});
</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
