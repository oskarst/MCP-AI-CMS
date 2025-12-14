<?php
/**
 * Admin Block Editor
 */

require_once __DIR__ . '/includes/auth-guard.php';
require_once __DIR__ . '/../core/PageManager.php';
require_once __DIR__ . '/../core/BlockParser.php';
require_once __DIR__ . '/../core/BackupManager.php';
require_once __DIR__ . '/../core/SitemapGenerator.php';
require_once __DIR__ . '/../core/PageSettings.php';
require_once __DIR__ . '/../core/CSRF.php';

$reservedFolders = $config['reserved_folders'] ?? ['cms'];
$backupManager = new BackupManager($config['backups_dir'], $config['max_backups_per_page']);
$sitemapGenerator = new SitemapGenerator($config['root_dir'], $config['base_url'] ?? 'http://localhost', $reservedFolders, $config['drafts_dir'] ?? null);
$pageSettings = new PageSettings($config['cms_dir'] . '/settings');
$pageManager = new PageManager($config['root_dir'], $reservedFolders, $config['drafts_dir'] ?? null, $backupManager, $sitemapGenerator, $pageSettings);
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

            case 'save_settings':
                $customCSS = $_POST['custom_css'] ?? '';

                $settings = [
                    'custom_css' => $customCSS
                ];

                $pageManager->savePageSettings($pageId, $settings);
                $successMessage = "Page settings saved successfully.";
                break;
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

$hasDraft = $pageManager->hasDraft($pageId);

// Load page settings
try {
    $currentSettings = $pageManager->getPageSettings($pageId);
} catch (Exception $e) {
    $currentSettings = [
        'custom_css' => '',
        'custom_styles' => '',
        'custom_stylesheets' => [],
        'created_at' => null,
        'updated_at' => null
    ];
    error_log("Failed to load page settings: " . $e->getMessage());
}

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

<!-- Ace Editor -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.6/ace.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.6/mode-php.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.6/theme-chrome.min.js"></script>
<style>
    .ace-editor-wrapper {
        border: 2px solid #e2e8f0;
        border-radius: 0.75rem;
        overflow: hidden;
    }
    .dark .ace-editor-wrapper {
        border-color: #2a2e33;
    }
    .ace_editor {
        font-family: 'JetBrains Mono', monospace !important;
        font-size: 14px !important;
        line-height: 1.6 !important;
    }
</style>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
        Edit Page: <code class="text-accent-600"><?php echo htmlspecialchars($pageId ?: '/'); ?></code>
        <?php if ($hasDraft): ?>
            <span class="ml-3 px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">Has Draft</span>
        <?php endif; ?>
    </h1>
    <div class="flex items-center gap-3 text-sm">
        <a href="/cms/admin/pages.php" class="text-accent-600 hover:text-accent-700">&larr; Back to Pages</a>
        <span class="text-gray-400 dark:text-gray-600">|</span>
        <a href="/cms/admin/preview.php?page_id=<?php echo urlencode($pageId); ?>" target="_blank" class="text-emerald-600 dark:text-emerald-400 hover:text-emerald-700">Preview Live</a>

        <?php if ($hasDraft): ?>
            <span class="text-gray-400 dark:text-gray-600">|</span>
            <a href="/cms/admin/preview.php?page_id=<?php echo urlencode($pageId); ?>&draft=1" target="_blank" class="text-amber-600 dark:text-amber-400 hover:text-amber-700">Preview Draft</a>
            <span class="text-gray-400 dark:text-gray-600">|</span>
            <form method="post" class="inline" onsubmit="return confirm('Publish this draft?');">
                <?php echo CSRF::inputField(); ?>
                <input type="hidden" name="action" value="publish">
                <button type="submit" class="text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 font-medium">Publish</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($successMessage)): ?>
    <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl p-4 mb-6 flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>
        <p class="text-emerald-800 dark:text-emerald-300 font-medium"><?php echo htmlspecialchars($successMessage); ?></p>
    </div>
<?php endif; ?>

<?php if (isset($errorMessage)): ?>
    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-4 mb-6 flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg bg-red-100 dark:bg-red-900/30 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </div>
        <p class="text-red-800 dark:text-red-300 font-medium"><?php echo htmlspecialchars($errorMessage); ?></p>
    </div>
<?php endif; ?>

<!-- Page Settings Accordion -->
<div class="bg-white dark:bg-dark-400 rounded-2xl shadow-soft border border-surface-200 dark:border-dark-200 mb-6" x-data="{ settingsOpen: false }">
    <button
        type="button"
        @click="settingsOpen = !settingsOpen"
        class="w-full flex items-center justify-between p-6 text-left hover:bg-surface-50 dark:hover:bg-dark-300 rounded-2xl transition">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white flex items-center gap-3">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
            <span>Page Settings</span>
        </h2>
        <svg
            class="w-5 h-5 text-gray-500 dark:text-gray-400 transition-transform"
            :class="settingsOpen ? 'rotate-180' : ''"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>

    <div x-show="settingsOpen" x-cloak class="border-t border-surface-200 dark:border-dark-200">
        <form method="post" class="p-6">
            <?php echo CSRF::inputField(); ?>
            <input type="hidden" name="action" value="save_settings">

            <div class="mb-4">
                <label class="block mb-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Custom CSS & Stylesheets</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400 block mt-1">
                        Paste stylesheet URLs, &lt;link&gt; tags, or &lt;style&gt; tags. All will be loaded in preview mode.
                    </span>
                </label>
                <textarea
                    name="custom_css"
                    rows="15"
                    class="w-full px-4 py-3 bg-surface-50 dark:bg-dark-300 border-2 border-surface-200 dark:border-dark-200 rounded-xl text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-accent-500 focus:ring-4 focus:ring-accent-500/10 transition-all font-mono text-sm"
                    placeholder="Examples:&#10;&#10;https://cdn.example.com/styles.css&#10;/assets/custom.css&#10;&#10;<link rel=&quot;stylesheet&quot; href=&quot;https://example.com/theme.css&quot;>&#10;&#10;<style>&#10;  .my-class { color: red; }&#10;</style>"
                ><?php echo htmlspecialchars($currentSettings['custom_css'] ?? ''); ?></textarea>
            </div>

            <div class="flex gap-3 items-center">
                <button type="submit" class="btn-primary px-5 py-2.5 text-white rounded-xl font-medium shadow-lg shadow-accent-500/25">
                    Save Settings
                </button>
                <?php if ($pageManager->hasPageSettings($pageId)): ?>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        Last updated: <?php echo htmlspecialchars($currentSettings['updated_at'] ?? 'Never'); ?>
                    </span>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if (empty($blocks)): ?>
    <div class="bg-white dark:bg-dark-400 rounded-2xl shadow-soft border border-surface-200 dark:border-dark-200 p-6">
        <p class="text-gray-600 dark:text-gray-400">No blocks found in this page.</p>
    </div>
<?php else: ?>
    <?php foreach ($blocks as $index => $block): ?>
        <div class="bg-white dark:bg-dark-400 rounded-2xl shadow-soft border border-surface-200 dark:border-dark-200 p-6 mb-6" x-data="blockEditor(<?php echo $index; ?>)">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Block: <code class="text-accent-600"><?php echo htmlspecialchars($block['name']); ?></code></h2>

                <!-- View Toggle (hide for system blocks) -->
                <?php if (!($block['system'] ?? false)): ?>
                <div class="flex bg-surface-100 dark:bg-dark-300 rounded-lg p-1">
                    <button
                        type="button"
                        @click="switchToCode()"
                        :class="view === 'code' ? 'bg-white dark:bg-dark-400 shadow-sm text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                        class="px-4 py-2 text-sm font-medium rounded-md transition-all">
                        Code
                    </button>
                    <button
                        type="button"
                        @click="view = 'preview'; updatePreview()"
                        :class="view === 'preview' ? 'bg-white dark:bg-dark-400 shadow-sm text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                        class="px-4 py-2 text-sm font-medium rounded-md transition-all">
                        Preview
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <div class="mb-4 flex flex-wrap gap-2">
                <?php if ($block['role']): ?>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400">
                        Role: <?php echo htmlspecialchars($block['role']); ?>
                    </span>
                <?php endif; ?>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $block['custom'] ? 'bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-400' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'; ?>">
                    <?php echo $block['custom'] ? 'Custom' : 'Global'; ?>
                </span>
                <?php if ($block['system'] ?? false): ?>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400">
                        System
                    </span>
                <?php endif; ?>
            </div>

            <form method="post" x-ref="form">
                <?php echo CSRF::inputField(); ?>
                <input type="hidden" name="action" value="update_block">
                <input type="hidden" name="block_name" value="<?php echo htmlspecialchars($block['name']); ?>">

                <label class="flex items-center mb-4 cursor-pointer">
                    <input type="checkbox" name="block_custom" <?php echo $block['custom'] ? 'checked' : ''; ?> class="mr-2 h-4 w-4 text-accent-600 rounded border-gray-300 dark:border-gray-600 focus:ring-accent-500">
                    <span class="text-sm text-gray-700 dark:text-gray-300">Mark as custom (per-page override)</span>
                </label>

                <!-- Code View -->
                <div x-show="view === 'code'">
                    <div class="mb-4">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 block">Block Content:</span>
                        <div class="ace-editor-wrapper">
                            <div x-ref="editor" class="w-full" style="height: 400px;"><?php echo htmlspecialchars($block['content']); ?></div>
                        </div>
                        <textarea x-ref="textarea" name="block_content" class="hidden"><?php echo htmlspecialchars($block['content']); ?></textarea>
                    </div>
                </div>

                <!-- Preview View (hide for system blocks) -->
                <?php if (!($block['system'] ?? false)): ?>
                <div x-show="view === 'preview'" x-cloak class="mb-4">
                    <div class="border-2 border-surface-200 dark:border-dark-200 rounded-xl p-4 bg-surface-50 dark:bg-dark-300 mb-2">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Preview with custom styling (isolated from admin CSS):</p>
                        <div class="preview-wrapper bg-white dark:bg-white border border-surface-200 dark:border-dark-200 rounded-lg p-4" style="max-height: 500px; overflow-y: auto;">
                            <iframe
                                x-ref="preview"
                                class="w-full border-0 min-h-[200px]"
                                style="height: 400px;"
                            ></iframe>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Preview is completely isolated from admin styles. Edit in Code View, then switch back to see changes.</p>
                    </div>
                </div>
                <?php endif; ?>

                <div class="flex gap-3">
                    <button type="submit" class="btn-primary px-5 py-2.5 text-white rounded-xl font-medium shadow-lg shadow-accent-500/25">Save Block</button>
                </div>
            </form>
        </div>
    <?php endforeach; ?>
<?php endif; ?>


<script>
// Page settings for CSS injection
const pageSettings = {
    customStyles: <?php echo json_encode($currentSettings['custom_styles'] ?? ''); ?>,
    customStylesheets: <?php echo json_encode($currentSettings['custom_stylesheets'] ?? []); ?>
};

// Block editor controller for Alpine.js
function blockEditor(index) {
    return {
        view: 'code',
        cssLoaded: false,
        aceEditor: null,

        init() {
            this.$nextTick(() => {
                this.initAce();
            });

            // Sync Ace content to textarea on form submit
            if (this.$refs.form) {
                this.$refs.form.addEventListener('submit', () => {
                    if (this.aceEditor && this.$refs.textarea) {
                        this.$refs.textarea.value = this.aceEditor.getValue();
                    }
                });
            }
        },

        initAce() {
            const editorEl = this.$refs.editor;
            if (!editorEl || this.aceEditor) return;

            this.aceEditor = ace.edit(editorEl);
            this.aceEditor.setTheme('ace/theme/chrome');
            this.aceEditor.session.setMode('ace/mode/php');
            this.aceEditor.setOptions({
                showPrintMargin: false,
                wrap: true,
                tabSize: 4,
                useSoftTabs: true
            });

            // Sync to hidden textarea on change
            this.aceEditor.session.on('change', () => {
                if (this.$refs.textarea) {
                    this.$refs.textarea.value = this.aceEditor.getValue();
                }
            });
        },

        updatePreview() {
            if (!this.cssLoaded) {
                this.cssLoaded = true;
            }
            this.renderPreview();
        },

        injectCustomCSS() {
            const iframe = this.$refs.preview;
            if (!iframe) return;

            const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;

            if (pageSettings.customStyles) {
                const styleEl = iframeDoc.createElement('style');
                styleEl.textContent = pageSettings.customStyles;
                iframeDoc.head.appendChild(styleEl);
            }

            if (pageSettings.customStylesheets && pageSettings.customStylesheets.length > 0) {
                pageSettings.customStylesheets.forEach(url => {
                    if (!url || url.trim() === '') return;
                    const linkEl = iframeDoc.createElement('link');
                    linkEl.rel = 'stylesheet';
                    linkEl.href = url.trim();
                    iframeDoc.head.appendChild(linkEl);
                });
            }
        },

        renderPreview() {
            const content = this.aceEditor ? this.aceEditor.getValue() : (this.$refs.textarea ? this.$refs.textarea.value : '');

            const iframe = this.$refs.preview;
            if (!iframe) return;

            const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;

            iframeDoc.open();
            iframeDoc.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                </head>
                <body style="margin: 0; padding: 16px; font-family: system-ui, -apple-system, sans-serif;">
                    ${content}
                </body>
                </html>
            `);
            iframeDoc.close();

            this.injectCustomCSS();
        },

        switchToCode() {
            this.view = 'code';
            this.$nextTick(() => {
                if (this.aceEditor) {
                    this.aceEditor.resize();
                }
            });
        }
    };
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
