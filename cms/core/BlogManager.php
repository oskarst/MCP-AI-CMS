<?php
/**
 * BlogManager - Manages blog collections, drafts, and publishing
 *
 * Responsibilities:
 * - Manage collections (blog, news, etc.)
 * - Store drafts under /cms/drafts/collection/slug/index.php
 * - Publish/unpublish posts to/from /collection/slug/index.php
 */

class BlogManager
{
    private string $rootDir;
    private string $draftsDir;
    private string $collectionsFile;
    private string $templatesFile;
    private array $collections;
    private array $templates;
    private $sitemapGenerator;
    private $backupManager;

    public function __construct(string $rootDir, string $draftsDir, $sitemapGenerator = null, $backupManager = null)
    {
        $this->rootDir = rtrim($rootDir, '/');
        $this->draftsDir = rtrim($draftsDir, '/');
        $this->collectionsFile = dirname($draftsDir) . '/config/collections.json';
        $this->templatesFile = dirname($draftsDir) . '/config/blog-templates.json';
        $this->sitemapGenerator = $sitemapGenerator;
        $this->backupManager = $backupManager;

        // Load collections and templates
        $this->loadCollections();
        $this->loadTemplates();
    }

    /**
     * Load collections from config file
     */
    private function loadCollections(): void
    {
        if (file_exists($this->collectionsFile)) {
            $data = json_decode(file_get_contents($this->collectionsFile), true);
            $this->collections = $data ?? [];
        } else {
            // Default collections
            $this->collections = [
                ['id' => 'blog', 'base_path' => 'blog', 'label' => 'Blog'],
            ];
        }
    }

    /**
     * Load templates from config file
     */
    private function loadTemplates(): void
    {
        if (file_exists($this->templatesFile)) {
            $data = json_decode(file_get_contents($this->templatesFile), true);
            $this->templates = $data ?? [];
        } else {
            // Fallback to empty templates
            $this->templates = [];
        }
    }

    /**
     * Get all collections
     */
    public function getCollections(): array
    {
        return $this->collections;
    }

    /**
     * Get collection by ID
     */
    public function getCollection(string $collectionId): ?array
    {
        foreach ($this->collections as $collection) {
            if ($collection['id'] === $collectionId) {
                return $collection;
            }
        }
        return null;
    }

    /**
     * List all posts in a collection (both drafts and published)
     */
    public function listPosts(string $collectionId): array
    {
        $collection = $this->getCollection($collectionId);
        if (!$collection) {
            throw new Exception("Collection not found: {$collectionId}");
        }

        $posts = [];

        // Get drafts
        $draftsPath = $this->draftsDir . '/' . $collectionId;
        if (is_dir($draftsPath)) {
            $drafts = $this->scanPostsInDirectory($draftsPath, 'draft');
            $posts = array_merge($posts, $drafts);
        }

        // Get published posts
        $publishedPath = $this->rootDir . '/' . $collection['base_path'];
        if (is_dir($publishedPath)) {
            $published = $this->scanPostsInDirectory($publishedPath, 'published');
            $posts = array_merge($posts, $published);
        }

        return $posts;
    }

    /**
     * Scan directory for posts
     */
    private function scanPostsInDirectory(string $directory, string $status): array
    {
        $posts = [];
        $items = scandir($directory);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $directory . '/' . $item;
            if (is_dir($itemPath) && file_exists($itemPath . '/index.php')) {
                $posts[] = [
                    'slug' => $item,
                    'status' => $status,
                    'path' => $itemPath . '/index.php',
                ];
            }
        }

        return $posts;
    }

    /**
     * Create a new draft post
     */
    public function createPost(string $collectionId, string $slug, string $content = ''): string
    {
        $collection = $this->getCollection($collectionId);
        if (!$collection) {
            throw new Exception("Collection not found: {$collectionId}");
        }

        // Sanitize slug
        $slug = $this->sanitizeSlug($slug);

        // Create draft directory
        $draftDir = $this->draftsDir . '/' . $collectionId . '/' . $slug;
        if (file_exists($draftDir)) {
            throw new Exception("Post already exists: {$slug}");
        }

        if (!mkdir($draftDir, 0755, true)) {
            throw new Exception("Failed to create draft directory");
        }

        // Create index.php with default content or provided content
        $postPath = $draftDir . '/index.php';

        if (empty($content)) {
            // Default template
            $content = $this->getDefaultPostTemplate($slug, $collection['label']);
        }

        file_put_contents($postPath, $content);

        return $postPath;
    }

    /**
     * Publish a draft post
     */
    public function publishPost(string $collectionId, string $slug): void
    {
        $collection = $this->getCollection($collectionId);
        if (!$collection) {
            throw new Exception("Collection not found: {$collectionId}");
        }

        $draftPath = $this->draftsDir . '/' . $collectionId . '/' . $slug;
        $publishPath = $this->rootDir . '/' . $collection['base_path'] . '/' . $slug;

        if (!is_dir($draftPath)) {
            throw new Exception("Draft not found: {$slug}");
        }

        if (file_exists($publishPath)) {
            throw new Exception("Published post already exists: {$slug}");
        }

        // Create published directory
        if (!is_dir(dirname($publishPath))) {
            mkdir(dirname($publishPath), 0755, true);
        }

        // Create backup of current published post before overwriting (if it exists)
        $publishedIndexPath = $publishPath . '/index.php';
        if (file_exists($publishedIndexPath) && $this->backupManager) {
            try {
                // Use collection/slug as the backup ID
                $backupId = $collectionId . '/' . $slug;
                $this->backupManager->createBackup($backupId, $publishedIndexPath);
            } catch (Exception $e) {
                // Backup failed, but don't stop publishing
                error_log("Backup failed during blog publish: " . $e->getMessage());
            }
        }

        // Move draft to published location
        if (!rename($draftPath, $publishPath)) {
            throw new Exception("Failed to publish post");
        }

        // Update meta block to mark as published
        $this->updatePublishedStatus($publishPath . '/index.php', true);

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
     * Unpublish a post (move back to drafts)
     */
    public function unpublishPost(string $collectionId, string $slug): void
    {
        $collection = $this->getCollection($collectionId);
        if (!$collection) {
            throw new Exception("Collection not found: {$collectionId}");
        }

        $publishPath = $this->rootDir . '/' . $collection['base_path'] . '/' . $slug;
        $draftPath = $this->draftsDir . '/' . $collectionId . '/' . $slug;

        if (!is_dir($publishPath)) {
            throw new Exception("Published post not found: {$slug}");
        }

        if (file_exists($draftPath)) {
            throw new Exception("Draft already exists: {$slug}");
        }

        // Create drafts directory if needed
        if (!is_dir(dirname($draftPath))) {
            mkdir(dirname($draftPath), 0755, true);
        }

        // Move published to draft location
        if (!rename($publishPath, $draftPath)) {
            throw new Exception("Failed to unpublish post");
        }

        // Update meta block to mark as draft
        $this->updatePublishedStatus($draftPath . '/index.php', false);
    }

    /**
     * Delete a post (draft or published)
     */
    public function deletePost(string $collectionId, string $slug, string $status): void
    {
        $collection = $this->getCollection($collectionId);
        if (!$collection) {
            throw new Exception("Collection not found: {$collectionId}");
        }

        if ($status === 'draft') {
            $postPath = $this->draftsDir . '/' . $collectionId . '/' . $slug;
        } else {
            $postPath = $this->rootDir . '/' . $collection['base_path'] . '/' . $slug;
        }

        if (!is_dir($postPath)) {
            throw new Exception("Post not found: {$slug}");
        }

        // Delete directory and contents
        $this->deleteDirectory($postPath);
    }

    /**
     * Get post path
     */
    public function getPostPath(string $collectionId, string $slug, string $status): ?string
    {
        $collection = $this->getCollection($collectionId);
        if (!$collection) {
            return null;
        }

        if ($status === 'draft') {
            $postPath = $this->draftsDir . '/' . $collectionId . '/' . $slug . '/index.php';
        } else {
            $postPath = $this->rootDir . '/' . $collection['base_path'] . '/' . $slug . '/index.php';
        }

        return file_exists($postPath) ? $postPath : null;
    }

    /**
     * Update published status in post meta
     */
    private function updatePublishedStatus(string $filePath, bool $published): void
    {
        if (!file_exists($filePath)) {
            return;
        }

        $content = file_get_contents($filePath);

        // Simple approach: add/update a PHP comment at the top with status
        $statusComment = "<?php /* POST_STATUS: " . ($published ? "published" : "draft") . " */ ?>\n";

        // Remove existing status comment if present
        $content = preg_replace('/<\?php\s+\/\*\s+POST_STATUS:.*?\*\/\s+\?>\s*\n?/', '', $content);

        // Add new status comment at the beginning
        $content = $statusComment . $content;

        file_put_contents($filePath, $content);
    }

    /**
     * Sanitize slug
     */
    private function sanitizeSlug(string $slug): string
    {
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9-_]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }

    /**
     * Delete directory recursively
     */
    private function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($dir);
    }

    /**
     * Get default post template
     */
    private function getDefaultPostTemplate(string $slug, string $collectionLabel): string
    {
        $title = ucwords(str_replace('-', ' ', $slug));
        $date = date('Y-m-d');

        // Use template from configuration if available
        if (!empty($this->templates['post_template'])) {
            $template = $this->templates['post_template'];

            // Replace placeholders
            $template = str_replace('{TITLE}', $title, $template);
            $template = str_replace('{DATE}', $date, $template);
            $template = str_replace('{COLLECTION_LABEL}', $collectionLabel, $template);

            return $template;
        }

        // Fallback to default template
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<?php /* CMS:BLOCK name=head role=meta start */ ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} - {$collectionLabel}</title>
    <meta name="description" content="{$title}">
    <meta name="author" content="">
    <meta name="date" content="{$date}">
<?php /* CMS:BLOCK name=head end */ ?>
</head>
<body>
<?php /* CMS:BLOCK name=header role=navigation start */ ?>
    <header>
        <nav>
            <a href="/">Home</a>
            <a href="/blog/">Blog</a>
        </nav>
    </header>
<?php /* CMS:BLOCK name=header end */ ?>

<?php /* CMS:BLOCK name=content start */ ?>
    <main>
        <article>
            <h1>{$title}</h1>
            <p class="date">{$date}</p>

            <p>Write your content here...</p>
        </article>
    </main>
<?php /* CMS:BLOCK name=content end */ ?>

<?php /* CMS:BLOCK name=footer start */ ?>
    <footer>
        <p>&copy; <?php echo date('Y'); ?> All rights reserved.</p>
    </footer>
<?php /* CMS:BLOCK name=footer end */ ?>
</body>
</html>
HTML;
    }
}
