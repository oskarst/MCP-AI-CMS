<?php
/**
 * Admin Block Editor
 */

require_once __DIR__ . '/includes/auth-guard.php';
require_once __DIR__ . '/../core/PageManager.php';
require_once __DIR__ . '/../core/BlockParser.php';
require_once __DIR__ . '/../core/BackupManager.php';
require_once __DIR__ . '/../core/GlobalBackupManager.php';
require_once __DIR__ . '/../core/SitemapGenerator.php';
require_once __DIR__ . '/../core/PageSettings.php';
require_once __DIR__ . '/../core/CSRF.php';

$reservedFolders = $config['reserved_folders'] ?? ['cms'];
$backupManager = new BackupManager($config['backups_dir'], $config['max_backups_per_page']);
$globalBackupManager = new GlobalBackupManager($config['backups_dir']);
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

                $successMessage = "Block saved as draft.";

                // If block is NOT custom, sync to all other pages (global block update)
                if (!$blockCustom) {
                    $allPages = $pageManager->listPages();

                    // Build list of pages to backup (those that have this block and it's not custom)
                    $pagesToBackup = [];
                    foreach ($allPages as $page) {
                        if ($page['id'] === $pageId) continue; // Skip source page

                        try {
                            $pageBlocks = $blockParser->parseBlocks($page['path']);
                            foreach ($pageBlocks as $block) {
                                if ($block['name'] === $blockName && !$block['custom']) {
                                    $pagesToBackup[$page['id']] = $page['path'];
                                    break;
                                }
                            }
                        } catch (Exception $e) {
                            // Skip pages that can't be parsed
                        }
                    }

                    // Create global backup before syncing
                    if (!empty($pagesToBackup)) {
                        $globalBackupManager->createGlobalBackup(
                            $pagesToBackup,
                            $blockName,
                            "Global update of block '{$blockName}'"
                        );

                        // Sync the block to other pages
                        $syncResults = $blockParser->updateBlockGlobally(
                            $allPages,
                            $blockName,
                            $blockContent,
                            $pageId
                        );

                        $syncCount = count($syncResults['updated']);
                        $skipCount = count($syncResults['skipped']);

                        if ($syncCount > 0) {
                            $successMessage .= " Synced to {$syncCount} other page(s).";
                        }
                        if ($skipCount > 0) {
                            $successMessage .= " Skipped {$skipCount} custom page(s).";
                        }
                    }
                }

                $successMessage .= " Preview or publish when ready.";
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

            case 'restore_backup':
                $timestamp = $_POST['timestamp'] ?? '';
                if (!$timestamp) {
                    throw new Exception("No backup timestamp provided");
                }
                $backupManager->restoreBackup($pageId, $timestamp, $pagePath);
                // Clear any existing draft since we restored
                if ($pageManager->hasDraft($pageId)) {
                    $pageManager->discardDraft($pageId);
                }
                $successMessage = "Backup restored successfully.";
                break;

            case 'restore_global_backup':
                $timestamp = $_POST['timestamp'] ?? '';
                if (!$timestamp) {
                    throw new Exception("No backup timestamp provided");
                }
                $results = $globalBackupManager->restoreGlobalBackup($timestamp, $pageManager);
                $restoredCount = count($results['restored']);
                $failedCount = count($results['failed']);

                $successMessage = "Restored {$restoredCount} page(s) from global backup.";
                if ($failedCount > 0) {
                    $successMessage .= " Failed to restore {$failedCount} page(s).";
                }

                // Clear any existing draft for current page since we restored
                if ($pageManager->hasDraft($pageId)) {
                    $pageManager->discardDraft($pageId);
                }
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

// Get backups for this page
$backups = $backupManager->listBackups($pageId);

// Get global backups that include this page
$allGlobalBackups = $globalBackupManager->listGlobalBackups();
$globalBackups = array_filter($allGlobalBackups, function($backup) use ($pageId) {
    return in_array($pageId, $backup['pages'] ?? []);
});

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

// Extract CSS file URLs from the page for preview
$pageContent = $hasDraft ? $pageManager->getDraft($pageId) : file_get_contents($pagePath);
$cssFiles = [];
if (preg_match_all('/<link[^>]+rel=["\']stylesheet["\'][^>]+href=["\']([^"\']+)["\']|<link[^>]+href=["\']([^"\']+)["\'][^>]+rel=["\']stylesheet["\']/', $pageContent, $matches)) {
    foreach ($matches[1] as $i => $url) {
        $cssUrl = $url ?: $matches[2][$i];
        if ($cssUrl) {
            $cssFiles[] = $cssUrl;
        }
    }
}

// Extract wrapper structure around each block for realistic preview
function extractBlockWrapper($content, $blockName) {
    // Find block start marker
    $pattern = '/<\?php\s+\/\*\s*CMS:BLOCK\s+[^*]*name=' . preg_quote($blockName, '/') . '[^*]*\s+start\s*\*\/\s*\?>/i';
    if (!preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE)) {
        return ['before' => '', 'after' => ''];
    }

    $blockStart = $match[0][1];

    // Get content before block, after <body>
    $beforeBlock = substr($content, 0, $blockStart);
    $bodyPos = stripos($beforeBlock, '<body');
    if ($bodyPos !== false) {
        // Find end of body tag
        $bodyEnd = strpos($beforeBlock, '>', $bodyPos);
        if ($bodyEnd !== false) {
            $beforeBlock = substr($beforeBlock, $bodyEnd + 1);
        }
    }

    // Find block end marker
    $endPattern = '/<\?php\s+\/\*\s*CMS:BLOCK\s+name=' . preg_quote($blockName, '/') . '\s+end\s*\*\/\s*\?>/i';
    if (!preg_match($endPattern, $content, $endMatch, PREG_OFFSET_CAPTURE)) {
        return ['before' => '', 'after' => ''];
    }

    $blockEnd = $endMatch[0][1] + strlen($endMatch[0][0]);

    // Get content after block, before </body>
    $afterBlock = substr($content, $blockEnd);
    $bodyClosePos = stripos($afterBlock, '</body>');
    if ($bodyClosePos !== false) {
        $afterBlock = substr($afterBlock, 0, $bodyClosePos);
    }

    // Strip other block markers and their content from wrapper
    $stripPattern = '/<\?php\s+\/\*\s*CMS:BLOCK\s+[^*]+start\s*\*\/\s*\?>.*?<\?php\s+\/\*\s*CMS:BLOCK\s+[^*]+end\s*\*\/\s*\?>/is';
    $beforeBlock = preg_replace($stripPattern, '', $beforeBlock);
    $afterBlock = preg_replace($stripPattern, '', $afterBlock);

    return [
        'before' => trim($beforeBlock),
        'after' => trim($afterBlock)
    ];
}

// Build wrapper data for each block
$blockWrappers = [];
foreach ($blocks as $block) {
    $blockWrappers[$block['name']] = extractBlockWrapper($pageContent, $block['name']);
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
            <span data-draft-badge class="ml-3 px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">Has Draft</span>
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

<!-- Backup Management Accordion -->
<?php $totalBackups = count($backups) + count($globalBackups); ?>
<div class="bg-white dark:bg-dark-400 rounded-2xl shadow-soft border border-surface-200 dark:border-dark-200 mb-6" x-data="{ backupsOpen: false, backupTab: 'page' }">
    <button
        type="button"
        @click="backupsOpen = !backupsOpen"
        class="w-full flex items-center justify-between p-6 text-left hover:bg-surface-50 dark:hover:bg-dark-300 rounded-2xl transition">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white flex items-center gap-3">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span>Version History</span>
            <?php if ($totalBackups > 0): ?>
                <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 dark:bg-dark-300 text-gray-600 dark:text-gray-400"><?php echo $totalBackups; ?></span>
            <?php endif; ?>
        </h2>
        <svg
            class="w-5 h-5 text-gray-500 dark:text-gray-400 transition-transform"
            :class="backupsOpen ? 'rotate-180' : ''"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>

    <div x-show="backupsOpen" x-cloak class="border-t border-surface-200 dark:border-dark-200">
        <!-- Tabs -->
        <div class="flex border-b border-surface-200 dark:border-dark-200">
            <button
                type="button"
                @click="backupTab = 'page'"
                :class="backupTab === 'page' ? 'border-accent-500 text-accent-600 dark:text-accent-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                class="flex-1 px-4 py-3 text-sm font-medium border-b-2 transition">
                Page Backups
                <?php if (count($backups) > 0): ?>
                    <span class="ml-1.5 px-1.5 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-dark-300"><?php echo count($backups); ?></span>
                <?php endif; ?>
            </button>
            <button
                type="button"
                @click="backupTab = 'global'"
                :class="backupTab === 'global' ? 'border-accent-500 text-accent-600 dark:text-accent-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                class="flex-1 px-4 py-3 text-sm font-medium border-b-2 transition">
                Global Backups
                <?php if (count($globalBackups) > 0): ?>
                    <span class="ml-1.5 px-1.5 py-0.5 text-xs rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400"><?php echo count($globalBackups); ?></span>
                <?php endif; ?>
            </button>
        </div>

        <!-- Page Backups Tab -->
        <div x-show="backupTab === 'page'" class="p-6">
            <?php if (empty($backups)): ?>
                <p class="text-gray-500 dark:text-gray-400 text-center py-4">No page backups available yet. Page backups are created when you publish changes to custom blocks.</p>
            <?php else: ?>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Page backups are created when you publish changes. These restore only this page.</p>
                <div class="space-y-3">
                    <?php foreach ($backups as $i => $backup): ?>
                        <div class="flex items-center justify-between p-4 bg-surface-50 dark:bg-dark-300 rounded-xl <?php echo $i === 0 ? 'border-2 border-emerald-200 dark:border-emerald-800' : ''; ?>">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-lg bg-gray-200 dark:bg-dark-200 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($backup['date']); ?>
                                        <?php if ($i === 0): ?>
                                            <span class="ml-2 px-2 py-0.5 text-xs font-medium rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400">Latest</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <?php
                                        $fileSize = file_exists($backup['path']) ? filesize($backup['path']) : 0;
                                        echo number_format($fileSize / 1024, 1) . ' KB';
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="/cms/admin/preview-backup.php?page_id=<?php echo urlencode($pageId); ?>&timestamp=<?php echo urlencode($backup['timestamp']); ?>"
                                   target="_blank"
                                   class="px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-dark-400 border border-gray-300 dark:border-dark-200 rounded-lg hover:bg-gray-50 dark:hover:bg-dark-300 transition">
                                    Preview
                                </a>
                                <form method="post" class="inline" onsubmit="return confirm('Restore this backup? This will replace the current live version.');">
                                    <?php echo CSRF::inputField(); ?>
                                    <input type="hidden" name="action" value="restore_backup">
                                    <input type="hidden" name="timestamp" value="<?php echo htmlspecialchars($backup['timestamp']); ?>">
                                    <button type="submit" class="px-3 py-1.5 text-sm font-medium text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg hover:bg-amber-100 dark:hover:bg-amber-900/30 transition">
                                        Restore
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Global Backups Tab -->
        <div x-show="backupTab === 'global'" class="p-6">
            <?php if (empty($globalBackups)): ?>
                <p class="text-gray-500 dark:text-gray-400 text-center py-4">No global backups available yet. Global backups are created when you edit blocks without the custom flag (header, footer, etc.).</p>
            <?php else: ?>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Global backups are created when editing shared blocks (header, footer). Restoring will revert <strong>all affected pages</strong> at once.</p>
                <div class="space-y-3">
                    <?php foreach ($globalBackups as $i => $gbackup): ?>
                        <div class="flex items-center justify-between p-4 bg-surface-50 dark:bg-dark-300 rounded-xl <?php echo $i === 0 ? 'border-2 border-blue-200 dark:border-blue-800' : ''; ?>">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($gbackup['date']); ?>
                                        <?php if ($i === 0): ?>
                                            <span class="ml-2 px-2 py-0.5 text-xs font-medium rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400">Latest</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        Block: <code class="text-accent-600"><?php echo htmlspecialchars($gbackup['block_name']); ?></code>
                                        &bull; <?php echo count($gbackup['pages']); ?> page(s) affected
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <form method="post" class="inline" onsubmit="return confirm('Restore this global backup?\n\nThis will revert <?php echo count($gbackup['pages']); ?> page(s) to their state from <?php echo htmlspecialchars($gbackup['date']); ?>.\n\nAffected pages:\n<?php echo htmlspecialchars(implode(', ', array_map(function($p) { return $p ?: '/'; }, $gbackup['pages']))); ?>');">
                                    <?php echo CSRF::inputField(); ?>
                                    <input type="hidden" name="action" value="restore_global_backup">
                                    <input type="hidden" name="timestamp" value="<?php echo htmlspecialchars($gbackup['timestamp']); ?>">
                                    <button type="submit" class="px-3 py-1.5 text-sm font-medium text-blue-700 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/30 transition">
                                        Restore All Pages
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (empty($blocks)): ?>
    <div class="bg-white dark:bg-dark-400 rounded-2xl shadow-soft border border-surface-200 dark:border-dark-200 p-6">
        <p class="text-gray-600 dark:text-gray-400">No blocks found in this page.</p>
    </div>
<?php else: ?>
    <?php foreach ($blocks as $index => $block): ?>
        <?php $isSystem = $block['system'] ?? false; ?>
        <div class="bg-white dark:bg-dark-400 rounded-2xl shadow-soft border border-surface-200 dark:border-dark-200 mb-6" x-data="blockEditor(<?php echo $index; ?>, '<?php echo htmlspecialchars($block['name'], ENT_QUOTES); ?>', <?php echo $isSystem ? 'true' : 'false'; ?>)">
            <!-- Block Header (clickable to expand/collapse) -->
            <div @click="collapsed = !collapsed" class="flex items-center justify-between p-6 cursor-pointer hover:bg-surface-50 dark:hover:bg-dark-300 rounded-t-2xl transition" :class="{ 'rounded-b-2xl': collapsed }">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-gray-400 transition-transform" :class="{ '-rotate-90': collapsed }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Block: <code class="text-accent-600"><?php echo htmlspecialchars($block['name']); ?></code></h2>
                </div>
                <div class="flex flex-wrap gap-2">
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
            </div>

            <!-- Block Content (collapsible) -->
            <div x-show="!collapsed" x-transition class="px-6 py-6 border-t border-surface-200 dark:border-dark-200">
            <form x-ref="form" @submit.prevent="saveBlock()">
                <input type="hidden" name="block_name" value="<?php echo htmlspecialchars($block['name']); ?>">

                <label class="flex items-center mb-4 cursor-pointer">
                    <input type="checkbox" x-ref="customCheckbox" <?php echo $block['custom'] ? 'checked' : ''; ?> class="mr-2 h-4 w-4 text-accent-600 rounded border-gray-300 dark:border-gray-600 focus:ring-accent-500">
                    <span class="text-sm text-gray-700 dark:text-gray-300">Mark as custom (per-page override)</span>
                </label>

                <?php if (!($block['system'] ?? false)): ?>
                <!-- View Toggle Buttons -->
                <div class="flex justify-end gap-2 mb-4">
                    <button type="button" @click="switchToCode()" :class="view === 'code' ? 'bg-accent-600 text-white' : 'bg-surface-100 dark:bg-dark-300 text-gray-700 dark:text-gray-300'" class="px-4 py-2 rounded-lg text-sm font-medium transition">Code</button>
                    <button type="button" @click="switchToPreview()" :class="view === 'preview' ? 'bg-accent-600 text-white' : 'bg-surface-100 dark:bg-dark-300 text-gray-700 dark:text-gray-300'" class="px-4 py-2 rounded-lg text-sm font-medium transition">Preview</button>
                </div>

                <!-- Code Editor -->
                <div x-show="view === 'code'" class="mb-4">
                    <div class="ace-editor-wrapper">
                        <div x-ref="editor" class="w-full" style="height: 400px;"><?php echo htmlspecialchars($block['content']); ?></div>
                    </div>
                    <textarea x-ref="textarea" name="block_content" class="hidden"><?php echo htmlspecialchars($block['content']); ?></textarea>
                </div>

                <!-- Preview (WYSIWYG) -->
                <div x-show="view === 'preview'" class="mb-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Click on text to edit directly. Changes sync back to code.</p>
                    <div class="border-2 border-surface-200 dark:border-dark-200 rounded-xl overflow-hidden bg-white" style="height: 400px;">
                        <iframe x-ref="preview" class="w-full h-full border-0"></iframe>
                    </div>
                </div>
                <?php else: ?>
                <!-- Code only for system blocks -->
                <div class="mb-4">
                    <div class="ace-editor-wrapper">
                        <div x-ref="editor" class="w-full" style="height: 400px;"><?php echo htmlspecialchars($block['content']); ?></div>
                    </div>
                    <textarea x-ref="textarea" name="block_content" class="hidden"><?php echo htmlspecialchars($block['content']); ?></textarea>
                </div>
                <?php endif; ?>

                <div class="flex gap-3">
                    <button type="submit" :disabled="saving" class="btn-primary px-5 py-2.5 text-white rounded-xl font-medium shadow-lg shadow-accent-500/25 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span x-show="!saving">Save Block</span>
                        <span x-show="saving" class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Saving...
                        </span>
                    </button>
                </div>
            </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>


<script>
// CSS files extracted from the page for preview
const pageCssFiles = <?php echo json_encode($cssFiles); ?>;

// Wrapper structure for each block
const blockWrappers = <?php echo json_encode($blockWrappers); ?>;

// Block editor controller for Alpine.js
// CSRF token for AJAX requests
const csrfToken = '<?php echo CSRF::getToken(); ?>';
const pageId = '<?php echo addslashes($pageId); ?>';

function blockEditor(index, blockName, isSystem = false) {
    return {
        aceEditor: null,
        blockName: blockName,
        isSystem: isSystem,
        view: isSystem ? 'code' : 'preview',
        collapsed: isSystem,
        saving: false,

        init() {
            // Watch for collapse changes to init Ace when expanded
            this.$watch('collapsed', (value) => {
                if (!value && !this.aceEditor) {
                    this.$nextTick(() => {
                        setTimeout(() => {
                            this.initAce();
                            if (!this.isSystem && this.view === 'preview') {
                                this.renderPreview();
                            }
                        }, 50);
                    });
                }
            });

            // Initialize immediately if not collapsed
            if (!this.collapsed) {
                this.$nextTick(() => {
                    this.initAce();
                    if (!this.isSystem && this.view === 'preview') {
                        setTimeout(() => this.renderPreview(), 100);
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

        switchToCode() {
            this.view = 'code';
            this.$nextTick(() => {
                if (this.aceEditor) {
                    this.aceEditor.resize();
                }
            });
        },

        switchToPreview() {
            this.view = 'preview';
            this.$nextTick(() => {
                this.renderPreview();
            });
        },

        renderPreview() {
            const content = this.aceEditor ? this.aceEditor.getValue() : (this.$refs.textarea ? this.$refs.textarea.value : '');

            const iframe = this.$refs.preview;
            if (!iframe) return;

            const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;

            // Build CSS links from page stylesheets
            const cssLinks = pageCssFiles.map(url => `<link rel="stylesheet" href="${url}">`).join('\n');

            // Get wrapper structure for this block
            const wrapper = blockWrappers[this.blockName] || { before: '', after: '' };

            // WYSIWYG styles for editable elements
            const wysiwygStyles = `
                <style>
                    [data-editable]:hover {
                        outline: 2px dashed #3b82f6 !important;
                        outline-offset: 2px !important;
                        cursor: text !important;
                    }
                    [data-editable]:focus {
                        outline: 2px solid #3b82f6 !important;
                        outline-offset: 2px !important;
                        background: rgba(59, 130, 246, 0.05) !important;
                    }
                </style>
            `;

            iframeDoc.open();
            iframeDoc.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    ${cssLinks}
                    ${wysiwygStyles}
                </head>
                <body>
                    ${wrapper.before}
                    <div id="editable-content">${content}</div>
                    ${wrapper.after}
                </body>
                </html>
            `);
            iframeDoc.close();

            // Make text elements editable after content loads
            setTimeout(() => this.setupWysiwyg(iframeDoc), 100);
        },

        setupWysiwyg(iframeDoc) {
            const editableContent = iframeDoc.getElementById('editable-content');
            if (!editableContent) return;

            // Text elements that should be editable
            const textSelectors = 'h1, h2, h3, h4, h5, h6, p, span, a, li, td, th, label, button, figcaption';
            const textElements = editableContent.querySelectorAll(textSelectors);

            textElements.forEach(el => {
                // Skip if element contains only other elements (no direct text)
                const hasDirectText = Array.from(el.childNodes).some(
                    node => node.nodeType === Node.TEXT_NODE && node.textContent.trim()
                );
                if (!hasDirectText && el.children.length > 0) return;

                // Skip PHP code markers
                if (el.textContent.includes('<?') || el.textContent.includes('?>')) return;

                el.setAttribute('contenteditable', 'true');
                el.setAttribute('data-editable', 'true');
                el.setAttribute('data-original', el.textContent);

                // Handle text changes on blur
                el.addEventListener('blur', () => {
                    const original = el.getAttribute('data-original');
                    const newText = el.textContent;

                    if (original !== newText && original && newText) {
                        this.updateSourceText(original, newText);
                        el.setAttribute('data-original', newText);
                    }
                });

                // Prevent Enter from creating new elements
                el.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        el.blur();
                    }
                });
            });
        },

        updateSourceText(originalText, newText) {
            if (!this.aceEditor) return;

            let source = this.aceEditor.getValue();

            // Check if text exists and replace all occurrences
            if (source.includes(originalText)) {
                source = source.split(originalText).join(newText);
                this.aceEditor.setValue(source, -1);
                if (this.$refs.textarea) {
                    this.$refs.textarea.value = source;
                }
            }
        },

        async saveBlock() {
            if (this.saving) return;

            this.saving = true;

            // Get content from Ace editor
            const content = this.aceEditor ? this.aceEditor.getValue() : (this.$refs.textarea ? this.$refs.textarea.value : '');
            const isCustom = this.$refs.customCheckbox ? this.$refs.customCheckbox.checked : false;

            const formData = new FormData();
            formData.append('action', 'update_block');
            formData.append('csrf_token', csrfToken);
            formData.append('block_name', this.blockName);
            formData.append('block_content', content);
            if (isCustom) {
                formData.append('block_custom', '1');
            }

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                if (response.ok) {
                    showToast('Block saved as draft', 'success');

                    // Update "Has Draft" badge if not already shown
                    const badge = document.querySelector('[data-draft-badge]');
                    if (!badge) {
                        // Add badge dynamically instead of reloading
                        const title = document.querySelector('h1');
                        if (title && !title.querySelector('[data-draft-badge]')) {
                            const badgeEl = document.createElement('span');
                            badgeEl.setAttribute('data-draft-badge', '');
                            badgeEl.className = 'ml-3 px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400';
                            badgeEl.textContent = 'Has Draft';
                            title.appendChild(badgeEl);
                        }
                    }
                } else {
                    throw new Error('Save failed');
                }
            } catch (error) {
                showToast('Error saving block', 'error');
            } finally {
                this.saving = false;
            }
        }
    };
}
</script>

<!-- Toast Notification -->
<div id="toast" class="fixed bottom-6 right-6 z-50 transition-all duration-300 transform translate-y-20 opacity-0 pointer-events-none">
    <div id="toast-content" class="flex items-center gap-3 px-5 py-4 rounded-xl shadow-lg border">
        <div id="toast-icon"></div>
        <span id="toast-message" class="font-medium"></span>
    </div>
</div>

<script>
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const content = document.getElementById('toast-content');
    const icon = document.getElementById('toast-icon');
    const msg = document.getElementById('toast-message');

    msg.textContent = message;

    if (type === 'success') {
        content.className = 'flex items-center gap-3 px-5 py-4 rounded-xl shadow-lg border bg-emerald-50 dark:bg-emerald-900/30 border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300';
        icon.innerHTML = '<svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
    } else {
        content.className = 'flex items-center gap-3 px-5 py-4 rounded-xl shadow-lg border bg-red-50 dark:bg-red-900/30 border-red-200 dark:border-red-800 text-red-800 dark:text-red-300';
        icon.innerHTML = '<svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
    }

    // Show
    toast.classList.remove('translate-y-20', 'opacity-0', 'pointer-events-none');
    toast.classList.add('translate-y-0', 'opacity-100');

    // Hide after 3 seconds
    setTimeout(() => {
        toast.classList.add('translate-y-20', 'opacity-0', 'pointer-events-none');
        toast.classList.remove('translate-y-0', 'opacity-100');
    }, 3000);
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
