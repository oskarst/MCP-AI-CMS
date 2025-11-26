<?php
/**
 * PageManager - Scans and manages page folders in the flat-file CMS.
 *
 * Pages are directories containing index.php files.
 * Page ID = relative path without leading slash (e.g., "", "about", "about/team")
 */

class PageManager
{
    private string $rootDir;
    private array $reservedFolders;
    private string $draftsDir;
    private $backupManager;
    private $sitemapGenerator;
    private $pageSettings;

    /**
     * @param string $rootDir Absolute path to the web root
     * @param array $reservedFolders List of reserved folder names
     * @param string|null $draftsDir Absolute path to drafts directory (optional)
     * @param object|null $backupManager Optional BackupManager instance for creating backups
     * @param object|null $sitemapGenerator Optional SitemapGenerator instance for updating sitemap
     * @param object|null $pageSettings Optional PageSettings instance for managing page settings
     */
    public function __construct(string $rootDir, array $reservedFolders = ['cms'], ?string $draftsDir = null, $backupManager = null, $sitemapGenerator = null, $pageSettings = null)
    {
        $this->rootDir = rtrim($rootDir, '/');
        $this->reservedFolders = $reservedFolders;
        $this->draftsDir = $draftsDir ? rtrim($draftsDir, '/') . '/pages' : '';
        $this->backupManager = $backupManager;
        $this->sitemapGenerator = $sitemapGenerator;
        $this->pageSettings = $pageSettings;
    }

    /**
     * List all pages in the content tree.
     *
     * @return array Array of pages, each with 'id' and 'path'
     */
    public function listPages(): array
    {
        $pages = [];

        // Add root page if it exists
        $rootIndexPath = $this->rootDir . '/index.php';
        if (file_exists($rootIndexPath)) {
            $pages[] = [
                'id' => 'index',
                'path' => $rootIndexPath,
            ];
        }

        // Recursively scan for page directories
        $this->scanDirectory($this->rootDir, '', $pages);

        // Sort by ID
        usort($pages, function ($a, $b) {
            return strcmp($a['id'], $b['id']);
        });

        return $pages;
    }

    /**
     * Get the file path for a specific page ID.
     *
     * @param string $pageId Page ID (e.g., "", "about", "about/team")
     * @return string|null Absolute path to index.php, or null if not found
     */
    public function getPagePath(string $pageId): ?string
    {
        $pageId = trim($pageId, '/');

        // Validate against path traversal attacks
        if (strpos($pageId, '..') !== false) {
            return null;
        }

        if ($pageId === '' || $pageId === 'index') {
            $path = $this->rootDir . '/index.php';
        } else {
            $path = $this->rootDir . '/' . $pageId . '/index.php';
        }

        // Ensure the resolved path is within the root directory
        if (file_exists($path)) {
            $realPath = realpath($path);
            $realRoot = realpath($this->rootDir);

            // Check if the resolved path is within the root directory
            if ($realPath && $realRoot && strpos($realPath, $realRoot) === 0) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Check if a page exists.
     *
     * @param string $pageId Page ID
     * @return bool True if page exists
     */
    public function pageExists(string $pageId): bool
    {
        return $this->getPagePath($pageId) !== null;
    }

    /**
     * Create a new page by duplicating an existing one.
     *
     * @param string $sourcePageId Source page ID to duplicate
     * @param string $newPageId New page ID
     * @return void
     * @throws Exception if source doesn't exist or target already exists
     */
    public function duplicatePage(string $sourcePageId, string $newPageId): void
    {
        $sourcePath = $this->getPagePath($sourcePageId);
        if (!$sourcePath) {
            throw new Exception("Source page '{$sourcePageId}' not found");
        }

        if ($this->pageExists($newPageId)) {
            throw new Exception("Target page '{$newPageId}' already exists");
        }

        // Check against reserved folder names
        $pageIdParts = explode('/', trim($newPageId, '/'));
        $firstPart = $pageIdParts[0] ?? '';
        if (in_array($firstPart, $this->reservedFolders)) {
            throw new Exception("Cannot use reserved folder name '{$firstPart}' as page ID");
        }

        $newPageId = trim($newPageId, '/');
        $targetDir = $newPageId === '' ? $this->rootDir : $this->rootDir . '/' . $newPageId;

        // Create target directory if needed
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                throw new Exception("Failed to create directory: {$targetDir}");
            }
        }

        $targetPath = $targetDir . '/index.php';

        // Copy the file
        if (!copy($sourcePath, $targetPath)) {
            throw new Exception("Failed to copy page");
        }

        // Copy page settings if they exist
        if ($this->pageSettings) {
            try {
                $this->pageSettings->copySettings($sourcePageId, $newPageId);
            } catch (Exception $e) {
                // Settings copy failed, but don't stop duplication
                error_log("Failed to copy page settings: " . $e->getMessage());
            }
        }
    }

    /**
     * Create a new page from HTML content.
     *
     * @param string $pageId New page ID
     * @param string $htmlContent HTML content for the page
     * @return void
     * @throws Exception if page already exists or creation fails
     */
    public function createPageFromHtml(string $pageId, string $htmlContent): void
    {
        if ($this->pageExists($pageId)) {
            throw new Exception("Page '{$pageId}' already exists");
        }

        // Check against reserved folder names
        $pageIdParts = explode('/', trim($pageId, '/'));
        $firstPart = $pageIdParts[0] ?? '';
        if (in_array($firstPart, $this->reservedFolders)) {
            throw new Exception("Cannot use reserved folder name '{$firstPart}' as page ID");
        }

        $pageId = trim($pageId, '/');
        $targetDir = $pageId === '' ? $this->rootDir : $this->rootDir . '/' . $pageId;

        // Create target directory if needed
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                throw new Exception("Failed to create directory: {$targetDir}");
            }
        }

        $targetPath = $targetDir . '/index.php';

        // Ensure HTML starts with <?php tag if it doesn't already
        if (strpos(trim($htmlContent), '<?php') !== 0) {
            $htmlContent = "<?php\n// Page created via CMS\n\n?>" . $htmlContent;
        }

        // Write the HTML content to the file
        if (file_put_contents($targetPath, $htmlContent) === false) {
            throw new Exception("Failed to create page file");
        }

        // Auto-extract CSS from HTML and save to page settings
        if ($this->pageSettings) {
            try {
                $extractedCSS = $this->extractCSSFromHTML($htmlContent);
                if (!empty($extractedCSS)) {
                    $this->pageSettings->saveSettings($pageId, [
                        'custom_css' => $extractedCSS
                    ]);
                }
            } catch (Exception $e) {
                // CSS extraction failed, but don't stop page creation
                error_log("Failed to extract CSS during page creation: " . $e->getMessage());
            }
        }
    }

    /**
     * Extract CSS links and style tags from HTML content
     *
     * @param string $html HTML content
     * @return string Extracted CSS (links and style tags)
     */
    private function extractCSSFromHTML(string $html): string
    {
        $extracted = [];

        // Extract <link rel="stylesheet"> tags
        preg_match_all('/<link[^>]*rel=["\']stylesheet["\'][^>]*>/i', $html, $linkMatches);
        if (!empty($linkMatches[0])) {
            $extracted = array_merge($extracted, $linkMatches[0]);
        }

        // Extract <style> tags with content
        preg_match_all('/<style[^>]*>.*?<\/style>/is', $html, $styleMatches);
        if (!empty($styleMatches[0])) {
            $extracted = array_merge($extracted, $styleMatches[0]);
        }

        return implode("\n\n", $extracted);
    }

    /**
     * Delete a page.
     *
     * @param string $pageId Page ID to delete
     * @return void
     * @throws Exception if page doesn't exist or cannot be deleted
     */
    public function deletePage(string $pageId): void
    {
        $pagePath = $this->getPagePath($pageId);
        if (!$pagePath) {
            throw new Exception("Page '{$pageId}' not found");
        }

        // Delete the index.php file
        if (!unlink($pagePath)) {
            throw new Exception("Failed to delete page file");
        }

        // Delete page settings if they exist
        if ($this->pageSettings) {
            try {
                $this->pageSettings->deleteSettings($pageId);
            } catch (Exception $e) {
                // Settings deletion failed, but don't stop page deletion
                error_log("Failed to delete page settings: " . $e->getMessage());
            }
        }

        // Try to remove the directory if empty (but don't fail if not empty)
        if ($pageId !== '') {
            $pageDir = dirname($pagePath);
            @rmdir($pageDir);
        }
    }

    /**
     * Recursively scan a directory for page folders.
     *
     * @param string $dir Absolute directory path
     * @param string $relPath Relative path from root (for building page IDs)
     * @param array &$pages Reference to pages array to populate
     * @return void
     */
    private function scanDirectory(string $dir, string $relPath, array &$pages): void
    {
        $items = @scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            // Skip reserved folders
            if (in_array($item, $this->reservedFolders)) {
                continue;
            }

            $itemPath = $dir . '/' . $item;

            if (is_dir($itemPath)) {
                // Check if this directory has an index.php
                $indexPath = $itemPath . '/index.php';
                if (file_exists($indexPath)) {
                    $pageId = $relPath === '' ? $item : $relPath . '/' . $item;
                    $pages[] = [
                        'id' => $pageId,
                        'path' => $indexPath,
                    ];
                }

                // Recursively scan subdirectories
                $newRelPath = $relPath === '' ? $item : $relPath . '/' . $item;
                $this->scanDirectory($itemPath, $newRelPath, $pages);
            }
        }
    }

    /**
     * Get the draft file path for a page ID.
     *
     * @param string $pageId Page ID
     * @return string|null Draft file path, or null if drafts not configured
     */
    public function getDraftPath(string $pageId): ?string
    {
        if (!$this->draftsDir) {
            return null;
        }

        $pageId = trim($pageId, '/');
        $safeName = $pageId === '' ? '__homepage__' : str_replace('/', '__', $pageId);
        return $this->draftsDir . '/' . $safeName . '.php';
    }

    /**
     * Check if a page has a draft.
     *
     * @param string $pageId Page ID
     * @return bool True if draft exists
     */
    public function hasDraft(string $pageId): bool
    {
        $draftPath = $this->getDraftPath($pageId);
        return $draftPath && file_exists($draftPath);
    }

    /**
     * Save page content as a draft.
     *
     * @param string $pageId Page ID
     * @param string $content Page content
     * @return void
     * @throws Exception if drafts not configured or save fails
     */
    public function saveDraft(string $pageId, string $content): void
    {
        $draftPath = $this->getDraftPath($pageId);
        if (!$draftPath) {
            throw new Exception('Drafts directory not configured');
        }

        // Ensure drafts directory exists
        if (!is_dir($this->draftsDir)) {
            if (!mkdir($this->draftsDir, 0755, true)) {
                throw new Exception('Failed to create drafts directory');
            }
        }

        // Save draft
        if (file_put_contents($draftPath, $content) === false) {
            throw new Exception('Failed to save draft');
        }
    }

    /**
     * Get draft content for a page.
     *
     * @param string $pageId Page ID
     * @return string|null Draft content, or null if no draft exists
     */
    public function getDraft(string $pageId): ?string
    {
        if (!$this->hasDraft($pageId)) {
            return null;
        }

        $draftPath = $this->getDraftPath($pageId);
        $content = file_get_contents($draftPath);
        return $content !== false ? $content : null;
    }

    /**
     * Publish a draft to the live page.
     *
     * @param string $pageId Page ID
     * @return void
     * @throws Exception if no draft exists or publish fails
     */
    public function publishDraft(string $pageId): void
    {
        if (!$this->hasDraft($pageId)) {
            throw new Exception("No draft exists for page '{$pageId}'");
        }

        $draftPath = $this->getDraftPath($pageId);
        $draftContent = file_get_contents($draftPath);

        if ($draftContent === false) {
            throw new Exception('Failed to read draft content');
        }

        // Get or create live page path
        $pageId = trim($pageId, '/');
        if ($pageId === '' || $pageId === 'index') {
            $livePath = $this->rootDir . '/index.php';
        } else {
            $liveDir = $this->rootDir . '/' . $pageId;

            // Create directory if it doesn't exist
            if (!is_dir($liveDir)) {
                if (!mkdir($liveDir, 0755, true)) {
                    throw new Exception('Failed to create page directory');
                }
            }

            $livePath = $liveDir . '/index.php';
        }

        // Create backup of current live page before overwriting
        if (file_exists($livePath) && $this->backupManager) {
            try {
                $this->backupManager->createBackup($pageId, $livePath);
            } catch (Exception $e) {
                // Backup failed, but don't stop publishing
                error_log("Backup failed during publish: " . $e->getMessage());
            }
        }

        // Write to live page
        if (file_put_contents($livePath, $draftContent) === false) {
            throw new Exception('Failed to publish draft to live page');
        }

        // Delete the draft
        @unlink($draftPath);

        // Regenerate sitemap
        if ($this->sitemapGenerator) {
            try {
                $this->sitemapGenerator->generate();
            } catch (Exception $e) {
                // Sitemap generation failed, but don't stop the publish
                error_log("Sitemap generation failed: " . $e->getMessage());
            }
        }
    }

    /**
     * Discard a draft.
     *
     * @param string $pageId Page ID
     * @return void
     * @throws Exception if no draft exists or delete fails
     */
    public function discardDraft(string $pageId): void
    {
        if (!$this->hasDraft($pageId)) {
            throw new Exception("No draft exists for page '{$pageId}'");
        }

        $draftPath = $this->getDraftPath($pageId);
        if (!unlink($draftPath)) {
            throw new Exception('Failed to discard draft');
        }
    }

    /**
     * Get settings for a page.
     *
     * @param string $pageId Page ID
     * @return array Page settings
     */
    public function getPageSettings(string $pageId): array
    {
        if (!$this->pageSettings) {
            return [
                'custom_styles' => '',
                'custom_stylesheets' => [],
                'created_at' => null,
                'updated_at' => null
            ];
        }

        return $this->pageSettings->getSettings($pageId);
    }

    /**
     * Save settings for a page.
     *
     * @param string $pageId Page ID
     * @param array $settings Settings to save
     * @return void
     * @throws Exception if settings manager not configured or save fails
     */
    public function savePageSettings(string $pageId, array $settings): void
    {
        if (!$this->pageSettings) {
            throw new Exception('Page settings manager not configured');
        }

        $this->pageSettings->saveSettings($pageId, $settings);
    }

    /**
     * Check if a page has settings.
     *
     * @param string $pageId Page ID
     * @return bool True if page has settings
     */
    public function hasPageSettings(string $pageId): bool
    {
        if (!$this->pageSettings) {
            return false;
        }

        return $this->pageSettings->hasSettings($pageId);
    }
}
