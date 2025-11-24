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

    /**
     * @param string $rootDir Absolute path to the web root
     * @param array $reservedFolders List of reserved folder names
     */
    public function __construct(string $rootDir, array $reservedFolders = ['cms'])
    {
        $this->rootDir = rtrim($rootDir, '/');
        $this->reservedFolders = $reservedFolders;
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
                'id' => '',
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

        if ($pageId === '') {
            $path = $this->rootDir . '/index.php';
        } else {
            $path = $this->rootDir . '/' . $pageId . '/index.php';
        }

        return file_exists($path) ? $path : null;
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
}
