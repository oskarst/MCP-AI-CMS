<?php
/**
 * Blog Templates Settings
 * Edits template files directly: blog-post.php, blog-list.php, blog-item.php
 */

require_once __DIR__ . '/includes/auth-guard.php';
require_once __DIR__ . '/../core/CSRF.php';

$templatesDir = __DIR__ . '/../blog-templates';
$blogConfigFile = __DIR__ . '/../config/blog-templates.json';

// Template files
$postTemplateFile = $templatesDir . '/blog-post.php';
$listTemplateFile = $templatesDir . '/blog-list.php';
$itemTemplateFile = $templatesDir . '/blog-item.php';

// Handle template updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verifyOrDie();

    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_templates') {
            // Save template files
            $postTemplate = $_POST['post_template'] ?? '';
            $listTemplate = $_POST['list_template'] ?? '';
            $itemTemplate = $_POST['item_template'] ?? '';

            file_put_contents($postTemplateFile, $postTemplate);
            file_put_contents($listTemplateFile, $listTemplate);
            file_put_contents($itemTemplateFile, $itemTemplate);

            header('Location: /cms/admin/blog-templates.php?saved=templates');
            exit;
        } elseif ($action === 'save_defaults') {
            // Save default values
            $blogConfig = file_exists($blogConfigFile) ? json_decode(file_get_contents($blogConfigFile), true) : [];

            $blogConfig['defaults'] = [
                'author' => $_POST['default_author'] ?? 'Dev Team',
                'excerpt' => $_POST['default_excerpt'] ?? 'Read this article on our blog.',
                'read_time' => $_POST['default_read_time'] ?? '5 min read'
            ];

            file_put_contents($blogConfigFile, json_encode($blogConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            header('Location: /cms/admin/blog-templates.php?saved=defaults');
            exit;
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Show success message after redirect
if (isset($_GET['saved'])) {
    $successMessage = $_GET['saved'] === 'templates' ? 'Templates saved successfully.' : 'Default values saved successfully.';
}

// Load current templates from files
$postTemplate = file_exists($postTemplateFile) ? file_get_contents($postTemplateFile) : '';
$listTemplate = file_exists($listTemplateFile) ? file_get_contents($listTemplateFile) : '';
$itemTemplate = file_exists($itemTemplateFile) ? file_get_contents($itemTemplateFile) : '';

// Load config for defaults
$blogConfig = file_exists($blogConfigFile) ? json_decode(file_get_contents($blogConfigFile), true) : [];
$defaults = $blogConfig['defaults'] ?? [
    'author' => 'Dev Team',
    'excerpt' => 'Read this article on the Developers Alliance blog.',
    'read_time' => '5 min read'
];

$pageTitle = 'Blog Templates';
$activePage = 'settings';

require __DIR__ . '/includes/header.php';
?>

<!-- Ace Editor -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.6/ace.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.6/mode-php.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.6/mode-html.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.6/theme-chrome.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.6/theme-monokai.min.js"></script>
<style>
    .ace-editor-wrapper {
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        overflow: hidden;
    }
    .dark .ace-editor-wrapper {
        border-color: #374151;
    }
    .ace_editor {
        font-family: 'JetBrains Mono', 'Fira Code', monospace !important;
        font-size: 13px !important;
    }
</style>

<h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-6">Blog Templates</h1>

<?php if (isset($successMessage)): ?>
    <div class="bg-green-50 dark:bg-green-900/20 border-l-4 border-green-500 p-4 mb-6">
        <p class="text-green-700 dark:text-green-400"><?php echo htmlspecialchars($successMessage); ?></p>
    </div>
<?php endif; ?>

<?php if (isset($errorMessage)): ?>
    <div class="bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 p-4 mb-6">
        <p class="text-red-700 dark:text-red-400"><?php echo htmlspecialchars($errorMessage); ?></p>
    </div>
<?php endif; ?>

<div class="mb-6">
    <a href="/cms/admin/settings.php" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">&larr; Back to Settings</a>
</div>

<!-- Default Values -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Default Values</h2>
    <form method="post">
        <?php echo CSRF::inputField(); ?>
        <input type="hidden" name="action" value="save_defaults">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Default Author</label>
                <input type="text" name="default_author" value="<?php echo htmlspecialchars($defaults['author']); ?>"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Default Excerpt</label>
                <input type="text" name="default_excerpt" value="<?php echo htmlspecialchars($defaults['excerpt']); ?>"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Default Read Time</label>
                <input type="text" name="default_read_time" value="<?php echo htmlspecialchars($defaults['read_time']); ?>"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
            </div>
        </div>

        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
            Save Defaults
        </button>
    </form>
</div>

<!-- Template Files -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6" x-data="blogTemplatesEditor()">
    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Template Files</h2>

    <div class="mb-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
        <p class="text-sm text-blue-800 dark:text-blue-300 mb-2"><strong>Available Placeholders:</strong></p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-sm text-blue-700 dark:text-blue-400">
            <div>
                <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">{{POST_TITLE}}</code> - Post title<br>
                <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">{{POST_SLUG}}</code> - URL slug<br>
                <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">{{POST_DATE}}</code> - Date (Y-m-d)<br>
                <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">{{POST_DATE_FORMATTED}}</code> - Formatted date
            </div>
            <div>
                <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">{{POST_EXCERPT}}</code> - Post excerpt<br>
                <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">{{POST_AUTHOR}}</code> - Author name<br>
                <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">{{COLLECTION_LABEL}}</code> - Blog section name
            </div>
            <div>
                <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">{{POST_CATEGORY}}</code> - Category (same as collection)<br>
                <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">{{POST_READING_TIME}}</code> - Read time (min)<br>
                <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">{{POST_TAGS}}</code> - Tags HTML
            </div>
        </div>
    </div>

    <form method="post" class="space-y-8" @submit="syncEditors()">
        <?php echo CSRF::inputField(); ?>
        <input type="hidden" name="action" value="save_templates">

        <!-- Post Item Template -->
        <div>
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Post Item Template</h3>
                <button type="button" @click="previewTemplate('item')"
                        class="inline-flex items-center gap-2 px-3 py-1.5 text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Preview
                </button>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">cms/blog-templates/blog-item.php</code> -
                Used for each post card in the blog listing.
            </p>
            <div class="ace-editor-wrapper">
                <div id="item-editor" style="height: 300px;"></div>
            </div>
            <textarea name="item_template" x-ref="itemTextarea" class="hidden"><?php echo htmlspecialchars($itemTemplate); ?></textarea>
        </div>

        <hr class="border-gray-200 dark:border-gray-700">

        <!-- List Page Template -->
        <div>
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Blog List Page Template</h3>
                <button type="button" @click="previewTemplate('list')"
                        class="inline-flex items-center gap-2 px-3 py-1.5 text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Preview
                </button>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">cms/blog-templates/blog-list.php</code> -
                Full page template for blog index. Use <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">{POSTS_LIST}</code> where posts should appear.
            </p>
            <div class="ace-editor-wrapper">
                <div id="list-editor" style="height: 400px;"></div>
            </div>
            <textarea name="list_template" x-ref="listTextarea" class="hidden"><?php echo htmlspecialchars($listTemplate); ?></textarea>
        </div>

        <hr class="border-gray-200 dark:border-gray-700">

        <!-- Post Template -->
        <div>
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Individual Blog Post Template</h3>
                <button type="button" @click="previewTemplate('post')"
                        class="inline-flex items-center gap-2 px-3 py-1.5 text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Preview
                </button>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">cms/blog-templates/blog-post.php</code> -
                Template used when creating new blog posts.
            </p>
            <div class="ace-editor-wrapper">
                <div id="post-editor" style="height: 500px;"></div>
            </div>
            <textarea name="post_template" x-ref="postTextarea" class="hidden"><?php echo htmlspecialchars($postTemplate); ?></textarea>
        </div>

        <div class="pt-4 flex items-center gap-4">
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                Save All Templates
            </button>
            <span class="text-sm text-gray-500 dark:text-gray-400">Preview shows current editor content with demo data</span>
        </div>
    </form>
</div>

<script>
function blogTemplatesEditor() {
    return {
        itemEditor: null,
        listEditor: null,
        postEditor: null,

        init() {
            this.$nextTick(() => {
                this.initEditors();
            });
        },

        initEditors() {
            const isDark = document.documentElement.classList.contains('dark');
            const theme = isDark ? 'ace/theme/monokai' : 'ace/theme/chrome';

            // Item editor
            this.itemEditor = ace.edit('item-editor');
            this.itemEditor.setTheme(theme);
            this.itemEditor.session.setMode('ace/mode/php');
            this.itemEditor.setOptions({
                showPrintMargin: false,
                wrap: true,
                tabSize: 4,
                useSoftTabs: true
            });
            this.itemEditor.setValue(this.$refs.itemTextarea.value, -1);

            // List editor
            this.listEditor = ace.edit('list-editor');
            this.listEditor.setTheme(theme);
            this.listEditor.session.setMode('ace/mode/php');
            this.listEditor.setOptions({
                showPrintMargin: false,
                wrap: true,
                tabSize: 4,
                useSoftTabs: true
            });
            this.listEditor.setValue(this.$refs.listTextarea.value, -1);

            // Post editor
            this.postEditor = ace.edit('post-editor');
            this.postEditor.setTheme(theme);
            this.postEditor.session.setMode('ace/mode/php');
            this.postEditor.setOptions({
                showPrintMargin: false,
                wrap: true,
                tabSize: 4,
                useSoftTabs: true
            });
            this.postEditor.setValue(this.$refs.postTextarea.value, -1);
        },

        syncEditors() {
            // Sync editor content to hidden textareas before form submit
            if (this.itemEditor) {
                this.$refs.itemTextarea.value = this.itemEditor.getValue();
            }
            if (this.listEditor) {
                this.$refs.listTextarea.value = this.listEditor.getValue();
            }
            if (this.postEditor) {
                this.$refs.postTextarea.value = this.postEditor.getValue();
            }
        },

        previewTemplate(type) {
            let content = '';

            switch (type) {
                case 'post':
                    content = this.postEditor ? this.postEditor.getValue() : this.$refs.postTextarea.value;
                    break;
                case 'list':
                    content = this.listEditor ? this.listEditor.getValue() : this.$refs.listTextarea.value;
                    break;
                case 'item':
                    content = this.itemEditor ? this.itemEditor.getValue() : this.$refs.itemTextarea.value;
                    break;
            }

            // Create a form and submit to preview in new tab
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/cms/admin/blog-template-preview.php?type=' + type;
            form.target = '_blank';

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'template_content';
            input.value = content;

            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
    };
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
