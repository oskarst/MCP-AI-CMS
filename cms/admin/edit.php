<?php
/**
 * Admin Block Editor
 */

require_once __DIR__ . '/includes/auth-guard.php';
require_once __DIR__ . '/../core/PageManager.php';
require_once __DIR__ . '/../core/BlockParser.php';
require_once __DIR__ . '/../core/BackupManager.php';
require_once __DIR__ . '/../core/SitemapGenerator.php';
require_once __DIR__ . '/../core/CSRF.php';

$reservedFolders = $config['reserved_folders'] ?? ['cms'];
$backupManager = new BackupManager($config['backups_dir'], $config['max_backups_per_page']);
$sitemapGenerator = new SitemapGenerator($config['root_dir'], $config['base_url'] ?? 'http://localhost', $reservedFolders, $config['drafts_dir'] ?? null);
$pageManager = new PageManager($config['root_dir'], $reservedFolders, $config['drafts_dir'] ?? null, $backupManager, $sitemapGenerator);
$blockParser = new BlockParser();

$pageId = $_GET['page_id'] ?? '';
$pagePath = $pageManager->getPagePath($pageId);

if (!$pagePath) {
    header('Location: /cms/admin/pages.php');
    exit;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verifyOrDie();

    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'update_block':
                $blockName = $_POST['block_name'] ?? '';
                $blockContent = $_POST['block_content'] ?? '';
                $blockCustom = isset($_POST['block_custom']) ? true : false;

                // Get draft content (from existing draft or live page)
                $draftContent = $pageManager->hasDraft($pageId)
                    ? $pageManager->getDraft($pageId)
                    : file_get_contents($pagePath);

                // Create temporary file to update the block
                $tempFile = tempnam(sys_get_temp_dir(), 'cms_block_edit_');
                file_put_contents($tempFile, $draftContent);

                // Update the block in the temp file
                $blockParser->updateBlock($tempFile, $blockName, $blockContent, $blockCustom);

                // Save the updated content as draft
                $pageManager->saveDraft($pageId, file_get_contents($tempFile));

                // Clean up
                @unlink($tempFile);

                $successMessage = "Block saved as draft. Preview or publish when ready.";
                break;

            case 'publish':
                $pageManager->publishDraft($pageId);
                $successMessage = "Draft published successfully.";
                break;
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

$hasDraft = $pageManager->hasDraft($pageId);

// Parse blocks from the page (use draft if exists, otherwise live)
try {
    if ($hasDraft) {
        // Parse from draft content
        $draftContent = $pageManager->getDraft($pageId);
        $tempFile = tempnam(sys_get_temp_dir(), 'cms_parse_');
        file_put_contents($tempFile, $draftContent);
        $blocks = $blockParser->parseBlocks($tempFile);
        @unlink($tempFile);
    } else {
        // Parse from live page
        $blocks = $blockParser->parseBlocks($pagePath);
    }
} catch (Exception $e) {
    $errorMessage = "Failed to parse blocks: " . $e->getMessage();
    $blocks = [];
}

$pageTitle = 'Edit Page: ' . ($pageId ?: '/');
$activePage = 'pages';

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
        Edit Page: <code class="text-blue-600"><?php echo htmlspecialchars($pageId ?: '/'); ?></code>
        <?php if ($hasDraft): ?>
            <span class="ml-3 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Has Draft</span>
        <?php endif; ?>
    </h1>
    <div class="flex items-center gap-3 text-sm">
        <a href="/cms/admin/pages.php" class="text-blue-600 hover:text-blue-800">&larr; Back to Pages</a>
        <span class="text-gray-400">|</span>
        <a href="/cms/admin/preview.php?page_id=<?php echo urlencode($pageId); ?>" target="_blank" class="text-green-600 hover:text-green-800">Preview Live</a>

        <?php if ($hasDraft): ?>
            <span class="text-gray-400">|</span>
            <a href="/cms/admin/preview.php?page_id=<?php echo urlencode($pageId); ?>&draft=1" target="_blank" class="text-orange-600 hover:text-orange-800">Preview Draft</a>
            <span class="text-gray-400">|</span>
            <form method="post" class="inline" onsubmit="return confirm('Publish this draft?');">
                <?php echo CSRF::inputField(); ?>
                <input type="hidden" name="action" value="publish">
                <button type="submit" class="text-green-600 hover:text-green-800 font-medium">Publish</button>
            </form>
        <?php endif; ?>
    </div>
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

<?php if (empty($blocks)): ?>
    <div class="bg-white rounded-lg shadow-md p-6">
        <p class="text-gray-600">No blocks found in this page.</p>
    </div>
<?php else: ?>
    <?php foreach ($blocks as $block): ?>
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-2xl font-semibold text-gray-900 mb-3">Block: <?php echo htmlspecialchars($block['name']); ?></h2>

            <div class="mb-4 text-sm text-gray-600">
                <?php if ($block['role']): ?>
                    <span class="inline-block bg-blue-100 text-blue-800 px-2 py-1 rounded mr-2">
                        Role: <?php echo htmlspecialchars($block['role']); ?>
                    </span>
                <?php endif; ?>
                <span class="inline-block <?php echo $block['custom'] ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800'; ?> px-2 py-1 rounded">
                    <?php echo $block['custom'] ? 'Custom' : 'Global'; ?>
                </span>
            </div>

            <form method="post">
                <?php echo CSRF::inputField(); ?>
                <input type="hidden" name="action" value="update_block">
                <input type="hidden" name="block_name" value="<?php echo htmlspecialchars($block['name']); ?>">

                <label class="flex items-center mb-4">
                    <input type="checkbox" name="block_custom" <?php echo $block['custom'] ? 'checked' : ''; ?> class="mr-2 h-4 w-4 text-blue-600 rounded">
                    <span class="text-sm text-gray-700">Mark as custom (per-page override)</span>
                </label>

                <label class="block mb-4">
                    <span class="text-sm font-medium text-gray-700 mb-2 block">Block Content:</span>
                    <textarea name="block_content" rows="10" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm"><?php echo htmlspecialchars($block['content']); ?></textarea>
                </label>

                <div class="flex gap-3">
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">Save Block</button>
                </div>
            </form>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize CodeMirror for all block content textareas
    document.querySelectorAll('textarea[name="block_content"]').forEach(function(textarea) {
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

<?php require __DIR__ . '/includes/footer.php'; ?>
