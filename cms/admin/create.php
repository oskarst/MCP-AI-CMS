<?php
/**
 * Admin Page Creation (via duplication or HTML)
 */

require_once __DIR__ . '/includes/auth-guard.php';
require_once __DIR__ . '/../core/PageManager.php';
require_once __DIR__ . '/../core/CSRF.php';

$reservedFolders = $config['reserved_folders'] ?? ['cms'];
$pageManager = new PageManager($config['root_dir'], $reservedFolders);
$pages = $pageManager->listPages();

// Handle page creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verifyOrDie();

    $creationType = $_POST['creation_type'] ?? 'duplicate';
    $parentPage = $_POST['parent_page'] ?? '';
    $pageName = trim($_POST['page_name'] ?? '', '/');

    try {
        if (!$pageName) {
            throw new Exception('Page name is required');
        }

        // Validate page name format (alphanumeric, hyphens only - no slashes)
        if (!preg_match('/^[a-z0-9\-]+$/i', $pageName)) {
            throw new Exception('Invalid page name format. Use only letters, numbers, and hyphens.');
        }

        // Combine parent and page name to create full page ID
        $newPageId = $parentPage ? trim($parentPage, '/') . '/' . $pageName : $pageName;

        if ($creationType === 'duplicate') {
            // Duplicate existing page
            $sourcePageId = $_POST['source_page_id'] ?? '';
            $pageManager->duplicatePage($sourcePageId, $newPageId);
        } else {
            // Create from HTML
            $htmlContent = $_POST['html_content'] ?? '';
            if (!$htmlContent) {
                throw new Exception('HTML content is required');
            }

            // Create page from HTML
            $pageManager->createPageFromHtml($newPageId, $htmlContent);
        }

        // Redirect to edit the new page
        header('Location: /cms/admin/edit.php?page_id=' . urlencode($newPageId));
        exit;

    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Create New Page';
$activePage = 'create';

require __DIR__ . '/includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900 mb-2">Create New Page</h1>
    <p class="text-gray-600">
        <a href="/cms/admin/pages.php" class="text-blue-600 hover:text-blue-800">&larr; Back to Pages</a>
    </p>
</div>

<?php if (isset($errorMessage)): ?>
    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
        <p class="text-red-700"><?php echo htmlspecialchars($errorMessage); ?></p>
    </div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow-md p-6">
    <!-- Tab Navigation -->
    <div class="flex border-b border-gray-200 mb-6">
        <button
            onclick="showTab('html')"
            id="tab-html"
            class="px-6 py-3 font-medium text-gray-700 border-b-2 border-blue-600 focus:outline-none"
        >
            Create from HTML
        </button>
        <button
            onclick="showTab('duplicate')"
            id="tab-duplicate"
            class="px-6 py-3 font-medium text-gray-500 border-b-2 border-transparent hover:text-gray-700 focus:outline-none"
        >
            Duplicate Page
        </button>
    </div>

    <!-- Create from HTML Tab -->
    <div id="content-html">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Create from HTML</h2>
        <p class="text-gray-600 mb-4">Paste complete HTML to create a new page. Include CMS block markers to define editable regions.</p>

        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
            <h3 class="font-semibold text-blue-900 mb-2">Block Syntax:</h3>
            <p class="text-sm text-blue-800 mb-2">Wrap editable content with block markers:</p>
            <code class="block bg-white px-3 py-2 rounded text-xs font-mono text-gray-800 mb-2">
                &lt;?php /* CMS:BLOCK name=block_name start */ ?&gt;<br>
                &nbsp;&nbsp;Your editable content here<br>
                &lt;?php /* CMS:BLOCK name=block_name end */ ?&gt;
            </code>
            <p class="text-sm text-blue-800 mt-3 mb-1"><strong>Optional attributes:</strong></p>
            <ul class="text-sm text-blue-800 list-disc list-inside space-y-1">
                <li><code class="bg-white px-1 rounded">role=meta</code> - For metadata blocks (title, description)</li>
                <li><code class="bg-white px-1 rounded">role=navigation</code> - For navigation menus</li>
                <li><code class="bg-white px-1 rounded">custom=true</code> - Skip this block during global sync</li>
            </ul>
        </div>

        <form method="post" class="space-y-6">
            <?php echo CSRF::inputField(); ?>
            <input type="hidden" name="creation_type" value="html">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Parent Page (optional):
                </label>
                <select name="parent_page" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">-- No Parent (Top Level) --</option>
                    <?php foreach ($pages as $page): ?>
                        <option value="<?php echo htmlspecialchars($page['id']); ?>">
                            <?php echo htmlspecialchars($page['id'] ?: '/ (Home)'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-1 text-sm text-gray-500">Select a parent page to create a sub-page, or leave blank for top-level page.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Page Name:
                </label>
                <input type="text" name="page_name" placeholder="e.g., about, team, contact" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                <p class="mt-1 text-sm text-gray-500">Use lowercase letters, numbers, and hyphens only. Select a parent page above to create nested pages.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    HTML Content:
                </label>
                <textarea name="html_content" rows="22" required placeholder="<!doctype html>
<html lang=&quot;en&quot;>
<head>
<?php /* CMS:BLOCK name=head role=meta start */ ?>
    <meta charset=&quot;utf-8&quot;>
    <title>My Page Title</title>
    <meta name=&quot;description&quot; content=&quot;Page description&quot;>
<?php /* CMS:BLOCK name=head role=meta end */ ?>
</head>
<body>
<?php /* CMS:BLOCK name=navbar role=navigation start */ ?>
    <nav>
        <a href=&quot;/&quot;>Home</a>
        <a href=&quot;/about&quot;>About</a>
    </nav>
<?php /* CMS:BLOCK name=navbar role=navigation end */ ?>

<?php /* CMS:BLOCK name=hero custom=true start */ ?>
    <header>
        <h1>Welcome to My Site</h1>
    </header>
<?php /* CMS:BLOCK name=hero custom=true end */ ?>

<?php /* CMS:BLOCK name=content start */ ?>
    <main>
        <p>Main page content goes here.</p>
    </main>
<?php /* CMS:BLOCK name=content end */ ?>

<?php /* CMS:BLOCK name=footer start */ ?>
    <footer>
        <p>&copy; 2024 My Company</p>
    </footer>
<?php /* CMS:BLOCK name=footer end */ ?>
</body>
</html>" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm"></textarea>
            </div>

            <div>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">Create Page</button>
            </div>
        </form>
    </div>

    <!-- Duplicate Page Tab -->
    <div id="content-duplicate" class="hidden">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Duplicate Existing Page</h2>
        <p class="text-gray-600 mb-6">Create a new page by duplicating an existing one. You can then edit the blocks in the new page.</p>

        <form method="post" class="space-y-6">
            <?php echo CSRF::inputField(); ?>
            <input type="hidden" name="creation_type" value="duplicate">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Source Page:
                </label>
                <select name="source_page_id" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">-- Select a page to duplicate --</option>
                    <?php foreach ($pages as $page): ?>
                        <option value="<?php echo htmlspecialchars($page['id']); ?>">
                            <?php echo htmlspecialchars($page['id'] ?: '/ (Home)'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Parent Page (optional):
                </label>
                <select name="parent_page" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">-- No Parent (Top Level) --</option>
                    <?php foreach ($pages as $page): ?>
                        <option value="<?php echo htmlspecialchars($page['id']); ?>">
                            <?php echo htmlspecialchars($page['id'] ?: '/ (Home)'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-1 text-sm text-gray-500">Select a parent page to create a sub-page, or leave blank for top-level page.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Page Name:
                </label>
                <input type="text" name="page_name" placeholder="e.g., about, team, contact" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                <p class="mt-1 text-sm text-gray-500">Use lowercase letters, numbers, and hyphens only. Select a parent page above to create nested pages.</p>
            </div>

            <div>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">Create Page</button>
            </div>
        </form>
    </div>
</div>

<script>
function showTab(tab) {
    // Hide all tabs
    document.getElementById('content-duplicate').classList.add('hidden');
    document.getElementById('content-html').classList.add('hidden');

    // Remove active state from all buttons
    document.getElementById('tab-duplicate').classList.remove('border-blue-600', 'text-gray-700');
    document.getElementById('tab-duplicate').classList.add('border-transparent', 'text-gray-500');
    document.getElementById('tab-html').classList.remove('border-blue-600', 'text-gray-700');
    document.getElementById('tab-html').classList.add('border-transparent', 'text-gray-500');

    // Show selected tab
    document.getElementById('content-' + tab).classList.remove('hidden');

    // Add active state to selected button
    document.getElementById('tab-' + tab).classList.remove('border-transparent', 'text-gray-500');
    document.getElementById('tab-' + tab).classList.add('border-blue-600', 'text-gray-700');
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
