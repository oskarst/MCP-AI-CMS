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

<!-- Page Settings Accordion -->
<div class="bg-white rounded-lg shadow-md mb-6" x-data="{ settingsOpen: false }">
    <button
        type="button"
        @click="settingsOpen = !settingsOpen"
        class="w-full flex items-center justify-between p-6 text-left hover:bg-gray-50 transition">
        <h2 class="text-xl font-semibold text-gray-900 flex items-center gap-2">
            <span>⚙️</span>
            <span>Page Settings</span>
        </h2>
        <svg
            class="w-5 h-5 text-gray-500 transition-transform"
            :class="settingsOpen ? 'rotate-180' : ''"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>

    <div x-show="settingsOpen" x-cloak class="border-t border-gray-200">
        <form method="post" class="p-6">
            <?php echo CSRF::inputField(); ?>
            <input type="hidden" name="action" value="save_settings">

            <div class="mb-4">
                <label class="block mb-2">
                    <span class="text-sm font-medium text-gray-700">Custom CSS & Stylesheets</span>
                    <span class="text-xs text-gray-500 block mt-1">
                        Paste stylesheet URLs, &lt;link&gt; tags, or &lt;style&gt; tags. All will be loaded in preview mode.
                    </span>
                </label>
                <textarea
                    name="custom_css"
                    rows="15"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                    placeholder="Examples:&#10;&#10;https://cdn.example.com/styles.css&#10;/assets/custom.css&#10;&#10;<link rel=&quot;stylesheet&quot; href=&quot;https://example.com/theme.css&quot;>&#10;&#10;<style>&#10;  .my-class { color: red; }&#10;</style>"
                ><?php echo htmlspecialchars($currentSettings['custom_css'] ?? ''); ?></textarea>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                    Save Settings
                </button>
                <?php if ($pageManager->hasPageSettings($pageId)): ?>
                    <span class="text-xs text-gray-500 self-center">
                        Last updated: <?php echo htmlspecialchars($currentSettings['updated_at'] ?? 'Never'); ?>
                    </span>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if (empty($blocks)): ?>
    <div class="bg-white rounded-lg shadow-md p-6">
        <p class="text-gray-600">No blocks found in this page.</p>
    </div>
<?php else: ?>
    <?php foreach ($blocks as $index => $block): ?>
        <div class="bg-white rounded-lg shadow-md p-6 mb-6" x-data="blockEditor(<?php echo $index; ?>)">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-2xl font-semibold text-gray-900">Block: <?php echo htmlspecialchars($block['name']); ?></h2>

                <!-- View Toggle (hide for system blocks) -->
                <?php if (!($block['system'] ?? false)): ?>
                <div class="flex border border-gray-300 rounded-md overflow-hidden">
                    <button
                        type="button"
                        @click="switchToCode()"
                        :class="view === 'code' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                        class="px-4 py-2 text-sm font-medium transition">
                        Code View
                    </button>
                    <button
                        type="button"
                        @click="view = 'preview'; updatePreview()"
                        :class="view === 'preview' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                        class="px-4 py-2 text-sm font-medium border-l border-gray-300 transition">
                        Preview View
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <div class="mb-4 text-sm text-gray-600">
                <?php if ($block['role']): ?>
                    <span class="inline-block bg-blue-100 text-blue-800 px-2 py-1 rounded mr-2">
                        Role: <?php echo htmlspecialchars($block['role']); ?>
                    </span>
                <?php endif; ?>
                <span class="inline-block <?php echo $block['custom'] ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800'; ?> px-2 py-1 rounded">
                    <?php echo $block['custom'] ? 'Custom' : 'Global'; ?>
                </span>
                <?php if ($block['system'] ?? false): ?>
                    <span class="inline-block bg-red-100 text-red-800 px-2 py-1 rounded ml-2">
                        System
                    </span>
                <?php endif; ?>
            </div>

            <form method="post" x-ref="form">
                <?php echo CSRF::inputField(); ?>
                <input type="hidden" name="action" value="update_block">
                <input type="hidden" name="block_name" value="<?php echo htmlspecialchars($block['name']); ?>">

                <label class="flex items-center mb-4">
                    <input type="checkbox" name="block_custom" <?php echo $block['custom'] ? 'checked' : ''; ?> class="mr-2 h-4 w-4 text-blue-600 rounded">
                    <span class="text-sm text-gray-700">Mark as custom (per-page override)</span>
                </label>

                <!-- Code View -->
                <div x-show="view === 'code'" x-cloak>
                    <label class="block mb-4">
                        <span class="text-sm font-medium text-gray-700 mb-2 block">Block Content:</span>
                        <textarea
                            x-ref="textarea"
                            name="block_content"
                            rows="10"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                        ><?php echo htmlspecialchars($block['content']); ?></textarea>
                    </label>
                </div>

                <!-- Preview View (hide for system blocks) -->
                <?php if (!($block['system'] ?? false)): ?>
                <div x-show="view === 'preview'" x-cloak class="mb-4">
                    <div class="border border-gray-300 rounded-md p-4 bg-gray-50 mb-2">
                        <p class="text-xs text-gray-500 mb-2">Preview with custom styling (isolated from admin CSS):</p>
                        <div class="preview-wrapper bg-white border border-gray-200 rounded p-4" style="max-height: 500px; overflow-y: auto;">
                            <iframe
                                x-ref="preview"
                                class="w-full border-0 min-h-[200px]"
                                style="height: 400px;"
                            ></iframe>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Preview is completely isolated from admin styles. Edit in Code View, then switch back to see changes.</p>
                    </div>
                </div>
                <?php endif; ?>

                <div class="flex gap-3">
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">Save Block</button>
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
        editor: null,
        cssLoaded: false,

        init() {
            // Wait for next tick to ensure DOM is ready
            this.$nextTick(() => {
                const textarea = this.$refs.textarea;

                if (!textarea) {
                    console.error('Textarea not found for block', index);
                    return;
                }

                try {
                    // Initialize CodeMirror for code view
                    this.editor = CodeMirror.fromTextArea(textarea, {
                        mode: 'application/x-httpd-php',
                        theme: 'material-darker',
                        lineNumbers: true,
                        lineWrapping: true,
                        indentUnit: 4,
                        indentWithTabs: false,
                        matchBrackets: true,
                        viewportMargin: Infinity
                    });

                    console.log('CodeMirror initialized for block', index);

                    // Refresh CodeMirror to ensure proper display
                    setTimeout(() => {
                        if (this.editor) {
                            this.editor.refresh();
                        }
                    }, 100);

                } catch (error) {
                    console.error('Failed to initialize CodeMirror:', error);
                }
            });

            // Save CodeMirror content to textarea before form submit
            this.$refs.form.addEventListener('submit', (e) => {
                if (this.editor) {
                    this.editor.save();
                } else {
                    console.warn('CodeMirror not initialized, using textarea value');
                }
            });
        },

        updatePreview() {
            // Inject custom CSS from page settings (only once)
            if (!this.cssLoaded) {
                this.injectCustomCSS();
                this.cssLoaded = true;
            }
            this.renderPreview();
        },

        injectCustomCSS() {
            const iframe = this.$refs.preview;
            if (!iframe) return;

            const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;

            // Inject custom styles into iframe
            if (pageSettings.customStyles) {
                const styleEl = iframeDoc.createElement('style');
                styleEl.textContent = pageSettings.customStyles;
                iframeDoc.head.appendChild(styleEl);
            }

            // Inject custom stylesheets into iframe
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
            // Get content from CodeMirror or textarea
            const content = this.editor ? this.editor.getValue() : this.$refs.textarea.value;

            // Write to iframe document
            const iframe = this.$refs.preview;
            if (!iframe) return;

            const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;

            // Create a basic HTML structure in iframe
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

            // Inject custom CSS after document is written
            this.injectCustomCSS();
        },

        // Make sure CodeMirror is visible when switching to code view
        switchToCode() {
            this.view = 'code';
            this.$nextTick(() => {
                if (this.editor) {
                    this.editor.refresh();
                }
            });
        }
    };
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
