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
                        <p class="text-xs text-gray-500 mb-2">Preview with frontend styling:</p>
                        <div class="preview-wrapper bg-white border border-gray-200 rounded p-4" style="max-height: 500px; overflow-y: auto;">
                            <div
                                x-ref="preview"
                                @input="syncFromPreview()"
                                contenteditable="true"
                                class="preview-content min-h-[200px] focus:outline-none focus:ring-2 focus:ring-blue-500"
                            ></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Edit content directly in the preview. HTML tags are preserved.</p>
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

<style>
/* Preview-specific CSS overrides */
.preview-content * {
    /* Override fixed/absolute positioning to prevent layout issues */
    position: static !important;
    top: auto !important;
    right: auto !important;
    bottom: auto !important;
    left: auto !important;
    z-index: auto !important;
}

/* Allow relative positioning for some elements */
.preview-content *[style*="position: relative"] {
    position: relative !important;
}

/* Reset some common fixed elements */
.preview-content nav,
.preview-content header,
.preview-content footer {
    position: static !important;
    width: auto !important;
}
</style>

<script>
// Load page CSS dynamically
let pageCSSLoaded = false;

function loadPageCSS(pageUrl) {
    if (pageCSSLoaded) return Promise.resolve();

    return fetch(pageUrl)
        .then(response => response.text())
        .then(html => {
            // Parse HTML to extract stylesheets
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            // Find all link tags with stylesheets
            const stylesheets = doc.querySelectorAll('link[rel="stylesheet"]');
            const promises = [];

            stylesheets.forEach(link => {
                const href = link.getAttribute('href');
                if (href) {
                    // Create new link element in admin page
                    const newLink = document.createElement('link');
                    newLink.rel = 'stylesheet';
                    newLink.href = href;
                    document.head.appendChild(newLink);

                    promises.push(new Promise(resolve => {
                        newLink.onload = resolve;
                        newLink.onerror = resolve; // Continue even if some CSS fails
                    }));
                }
            });

            // Also extract inline styles
            const styles = doc.querySelectorAll('style');
            styles.forEach(style => {
                const newStyle = document.createElement('style');
                newStyle.textContent = style.textContent;
                document.head.appendChild(newStyle);
            });

            pageCSSLoaded = true;
            return Promise.all(promises);
        })
        .catch(error => {
            console.error('Failed to load page CSS:', error);
        });
}

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
                        autoCloseTags: true,
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
            // Load page CSS first (only once)
            if (!this.cssLoaded) {
                const pageId = '<?php echo addslashes($pageId); ?>';
                const pageUrl = pageId ? '/' + pageId + '/' : '/';

                loadPageCSS(pageUrl).then(() => {
                    this.cssLoaded = true;
                    this.renderPreview();
                });
            } else {
                this.renderPreview();
            }
        },

        renderPreview() {
            // Get content from CodeMirror or textarea
            const content = this.editor ? this.editor.getValue() : this.$refs.textarea.value;

            // Set preview content
            if (this.$refs.preview) {
                this.$refs.preview.innerHTML = content;
            }
        },

        syncFromPreview() {
            // Get content from preview (preserves HTML)
            const content = this.$refs.preview.innerHTML;

            // Update CodeMirror
            if (this.editor) {
                this.editor.setValue(content);
            }

            // Update textarea
            this.$refs.textarea.value = content;
        },

        // Make sure CodeMirror is visible when switching to code view
        switchToCode() {
            // Sync any changes from preview before switching
            if (this.$refs.preview && this.$refs.preview.innerHTML) {
                this.syncFromPreview();
            }

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
