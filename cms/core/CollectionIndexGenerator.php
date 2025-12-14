<?php
/**
 * CollectionIndexGenerator - Generates index pages for blog collections
 *
 * Responsibilities:
 * - Generate index.php for each collection (e.g., /blog/index.php, /news/index.php)
 * - Extract metadata from posts (title, date, excerpt)
 * - Apply templates to generate listing pages
 */

require_once __DIR__ . '/Pagination.php';
require_once __DIR__ . '/PostMetaParser.php';

class CollectionIndexGenerator
{
    private string $rootDir;
    private string $templatesFile;
    private array $templates;
    private PostMetaParser $metaParser;

    public function __construct(string $rootDir, string $templatesFile)
    {
        $this->rootDir = rtrim($rootDir, '/');
        $this->templatesFile = $templatesFile;
        $this->metaParser = new PostMetaParser();
        $this->loadTemplates();
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
            $this->templates = [];
        }
    }

    /**
     * Generate index page for a collection
     */
    public function generateIndex(array $collection, array $posts): void
    {
        $indexType = $collection['index_type'] ?? 'auto';

        if ($indexType === 'auto') {
            $this->generateStaticIndex($collection, $posts);
        } else {
            $this->generateDynamicIndex($collection);
        }
    }

    /**
     * Generate static index page with pagination
     */
    private function generateStaticIndex(array $collection, array $posts): void
    {
        $listTemplate = $this->loadListTemplate();
        $postItemTemplate = $this->loadPostItemTemplate();

        // Filter only published posts
        $publishedPosts = array_filter($posts, fn($post) => $post['status'] === 'published');

        // Get collection settings
        $sortBy = $collection['sort_by'] ?? 'date';
        $sortOrder = $collection['sort_order'] ?? 'desc';
        $showExcerpts = $collection['show_excerpts'] ?? true;
        $postsPerPage = $collection['posts_per_page'] ?? 10;

        // Sort posts (featured first, then by date/title)
        usort($publishedPosts, function($a, $b) use ($sortBy, $sortOrder) {
            $metaA = $this->metaParser->extractMetadata($a['path']);
            $metaB = $this->metaParser->extractMetadata($b['path']);

            // Featured posts always come first
            $featuredA = $metaA['options']['featured'] ?? false;
            $featuredB = $metaB['options']['featured'] ?? false;

            if ($featuredA && !$featuredB) return -1;
            if (!$featuredA && $featuredB) return 1;

            // Then sort by title or date
            if ($sortBy === 'title') {
                $titleA = $metaA['content']['title'] ?? '';
                $titleB = $metaB['content']['title'] ?? '';
                $result = strcmp($titleA, $titleB);
            } else {
                // Default: sort by date
                $dateA = $metaA['dates']['published'] ?? '';
                $dateB = $metaB['dates']['published'] ?? '';
                $result = strcmp($dateA, $dateB);
            }

            return $sortOrder === 'asc' ? $result : -$result;
        });

        // Calculate total pages
        $totalPosts = count($publishedPosts);
        $pagination = new Pagination($totalPosts, $postsPerPage);
        $totalPages = $pagination->getTotalPages();

        // Clean up old pagination pages first
        $this->cleanupPaginationPages($collection['base_path']);

        // Generate each page
        for ($page = 1; $page <= $totalPages; $page++) {
            $pagination = new Pagination($totalPosts, $postsPerPage, $page);
            $offset = $pagination->getOffset();
            $limit = $pagination->getLimit();

            // Get posts for this page
            $pagePosts = array_slice($publishedPosts, $offset, $limit);

            // Generate posts list HTML
            $postsListHtml = '';
            foreach ($pagePosts as $post) {
                $meta = $this->metaParser->extractMetadata($post['path']);

                $postHtml = $postItemTemplate;

                // Replace all placeholders with metadata
                $postHtml = str_replace('{TITLE}', htmlspecialchars($meta['content']['title']), $postHtml);
                $postHtml = str_replace('{DATE}', htmlspecialchars($meta['dates']['published']), $postHtml);

                // Formatted date (December 14, 2025)
                $dateFormatted = '';
                if (!empty($meta['dates']['published'])) {
                    $timestamp = strtotime($meta['dates']['published']);
                    if ($timestamp) {
                        $dateFormatted = date('F j, Y', $timestamp);
                    }
                }
                $postHtml = str_replace('{DATE_FORMATTED}', htmlspecialchars($dateFormatted), $postHtml);
                $postHtml = str_replace('{READING_TIME}', $meta['content']['reading_time'], $postHtml);
                $postHtml = str_replace('{WORD_COUNT}', $meta['content']['word_count'], $postHtml);
                $postHtml = str_replace('{FEATURED_IMAGE}', htmlspecialchars($meta['media']['featured_image']), $postHtml);

                // Author (show only if exists)
                $authorHtml = !empty($meta['author']['name'])
                    ? ' • by ' . htmlspecialchars($meta['author']['name'])
                    : '';
                $postHtml = str_replace('{AUTHOR}', $authorHtml, $postHtml);

                // Author name (plain, defaults to Dev Team)
                $authorName = !empty($meta['author']['name']) ? $meta['author']['name'] : 'Dev Team';
                $postHtml = str_replace('{AUTHOR_NAME}', htmlspecialchars($authorName), $postHtml);

                // Categories and tags (comma-separated, show only if exist)
                $categoriesHtml = !empty($meta['taxonomy']['categories'])
                    ? ' • ' . htmlspecialchars(implode(', ', $meta['taxonomy']['categories']))
                    : '';
                $tagsHtml = !empty($meta['taxonomy']['tags'])
                    ? ' • Tags: ' . htmlspecialchars(implode(', ', $meta['taxonomy']['tags']))
                    : '';
                $postHtml = str_replace('{CATEGORIES}', $categoriesHtml, $postHtml);
                $postHtml = str_replace('{TAGS}', $tagsHtml, $postHtml);

                // Only show excerpt if enabled
                if ($showExcerpts) {
                    $postHtml = str_replace('{EXCERPT}', htmlspecialchars($meta['content']['excerpt']), $postHtml);
                } else {
                    $postHtml = str_replace('{EXCERPT}', '', $postHtml);
                }

                $postHtml = str_replace('{SLUG}', htmlspecialchars($post['slug']), $postHtml);
                $postHtml = str_replace('{COLLECTION_BASE_PATH}', htmlspecialchars($collection['base_path']), $postHtml);

                // Featured badge (shows only if post is featured)
                $isFeatured = $meta['options']['featured'] ?? false;
                $featuredBadge = $isFeatured
                    ? '<span class="px-3 py-1 bg-primary/10 text-primary text-sm font-semibold rounded-full">Featured</span>'
                    : '';
                $postHtml = str_replace('{FEATURED_BADGE}', $featuredBadge, $postHtml);

                $postsListHtml .= $postHtml . "\n";
            }

            // If no posts, show empty message
            if (empty($postsListHtml)) {
                $postsListHtml = '<p>No posts yet.</p>';
            }

            // Generate pagination navigation
            $paginationHtml = $totalPages > 1 ? $pagination->generateHTML('/' . $collection['base_path']) : '';

            // Generate final index HTML
            $indexHtml = $listTemplate;
            $indexHtml = str_replace('{COLLECTION_LABEL}', htmlspecialchars($collection['label']), $indexHtml);
            $indexHtml = str_replace('{POSTS_LIST}', $postsListHtml, $indexHtml);
            $indexHtml = str_replace('{PAGINATION}', $paginationHtml, $indexHtml);

            // Write to file
            if ($page === 1) {
                // First page goes to /blog/index.php
                $indexPath = $this->rootDir . '/' . $collection['base_path'] . '/index.php';
            } else {
                // Other pages go to /blog/page/2/index.php
                $indexPath = $this->rootDir . '/' . $collection['base_path'] . '/page/' . $page . '/index.php';
            }

            $indexDir = dirname($indexPath);
            if (!is_dir($indexDir)) {
                mkdir($indexDir, 0755, true);
            }

            file_put_contents($indexPath, $indexHtml);
        }
    }

    /**
     * Clean up old pagination pages
     */
    private function cleanupPaginationPages(string $basePath): void
    {
        $pageDir = $this->rootDir . '/' . $basePath . '/page';

        if (is_dir($pageDir)) {
            $this->deleteDirectory($pageDir);
        }
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
     * Generate dynamic index page (PHP that scans directory)
     */
    private function generateDynamicIndex(array $collection): void
    {
        $indexPath = $this->rootDir . '/' . $collection['base_path'] . '/index.php';
        $indexDir = dirname($indexPath);

        if (!is_dir($indexDir)) {
            mkdir($indexDir, 0755, true);
        }

        // Create PHP code that dynamically lists posts
        $dynamicCode = <<<'PHP'
<?php
/**
 * Dynamic blog index - automatically lists all posts in this collection
 * Generated by CollectionIndexGenerator
 */

// Scan current directory for post directories
$posts = [];
$currentDir = __DIR__;
$items = scandir($currentDir);

foreach ($items as $item) {
    if ($item === '.' || $item === '..' || $item === 'index.php') {
        continue;
    }

    $itemPath = $currentDir . '/' . $item;
    if (is_dir($itemPath) && file_exists($itemPath . '/index.php')) {
        // Extract post metadata
        $postContent = file_get_contents($itemPath . '/index.php');

        // Extract title
        preg_match('/<title>(.*?)<\/title>/i', $postContent, $titleMatch);
        $title = $titleMatch[1] ?? ucwords(str_replace('-', ' ', $item));

        // Extract date from meta tag
        preg_match('/<meta[^>]+name=["\']date["\'][^>]+content=["\']([^"\']+)/i', $postContent, $dateMatch);
        $date = $dateMatch[1] ?? '';

        // Extract excerpt from meta description
        preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)/i', $postContent, $excerptMatch);
        $excerpt = $excerptMatch[1] ?? '';

        $posts[] = [
            'slug' => $item,
            'title' => $title,
            'date' => $date,
            'excerpt' => $excerpt,
        ];
    }
}

// Sort by date (newest first)
usort($posts, function($a, $b) {
    return strcmp($b['date'], $a['date']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog</title>
</head>
<body>
    <header>
        <nav>
            <a href="/">Home</a>
        </nav>
    </header>

    <main>
        <h1>Blog</h1>
        <div class="posts-list">
            <?php if (empty($posts)): ?>
                <p>No posts yet.</p>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <article class="post-item">
                        <h2><a href="<?php echo htmlspecialchars($post['slug']); ?>/"><?php echo htmlspecialchars($post['title']); ?></a></h2>
                        <p class="date"><?php echo htmlspecialchars($post['date']); ?></p>
                        <p class="excerpt"><?php echo htmlspecialchars($post['excerpt']); ?></p>
                        <a href="<?php echo htmlspecialchars($post['slug']); ?>/">Read more</a>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> All rights reserved.</p>
    </footer>
</body>
</html>
PHP;

        file_put_contents($indexPath, $dynamicCode);
    }

    /**
     * Extract metadata from a post (legacy method for backward compatibility)
     *
     * @deprecated Use PostMetaParser->extractMetadata() instead for full metadata
     */
    public function extractPostMeta(string $postPath): array
    {
        $metadata = $this->metaParser->extractMetadata($postPath);

        // Return in old format for backward compatibility
        return [
            'title' => $metadata['content']['title'],
            'date' => $metadata['dates']['published'],
            'excerpt' => $metadata['content']['excerpt'],
        ];
    }

    /**
     * Load list template from file or fallback to default
     */
    private function loadListTemplate(): string
    {
        // Try to load from file first
        if (!empty($this->templates['list_template_file'])) {
            $cmsDir = dirname(dirname($this->templatesFile));
            $templatePath = $cmsDir . '/' . $this->templates['list_template_file'];

            if (file_exists($templatePath)) {
                return file_get_contents($templatePath);
            }
        }

        // Legacy support: inline template string
        if (!empty($this->templates['list_template'])) {
            return $this->templates['list_template'];
        }

        // Fallback to default
        return $this->getDefaultListTemplate();
    }

    /**
     * Load post item template from file or fallback to default
     */
    private function loadPostItemTemplate(): string
    {
        // Try to load from file first
        if (!empty($this->templates['post_item_template_file'])) {
            $cmsDir = dirname(dirname($this->templatesFile));
            $templatePath = $cmsDir . '/' . $this->templates['post_item_template_file'];

            if (file_exists($templatePath)) {
                return file_get_contents($templatePath);
            }
        }

        // Legacy support: inline template string
        if (!empty($this->templates['post_item_template'])) {
            return $this->templates['post_item_template'];
        }

        // Fallback to default
        return $this->getDefaultPostItemTemplate();
    }

    /**
     * Default list template
     */
    private function getDefaultListTemplate(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<?php /* CMS:BLOCK name=head role=meta start */ ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{COLLECTION_LABEL}</title>
    <meta name="description" content="Browse all {COLLECTION_LABEL} posts">
    <style>
        /* Simple pagination styles */
        .pagination { margin: 2rem 0; }
        .pagination-list { display: flex; list-style: none; gap: 0.5rem; padding: 0; flex-wrap: wrap; }
        .pagination-link, .pagination-previous, .pagination-next, .pagination-ellipsis {
            padding: 0.5rem 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }
        .pagination-link:hover, .pagination-previous:hover, .pagination-next:hover { background: #f5f5f5; }
        .pagination-link.active { background: #007bff; color: white; border-color: #007bff; }
        .pagination-previous.disabled, .pagination-next.disabled { opacity: 0.5; cursor: not-allowed; }
        .pagination-ellipsis { border: none; }
    </style>
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
        <h1>{COLLECTION_LABEL}</h1>
        <div class="posts-list">
            {POSTS_LIST}
        </div>
        {PAGINATION}
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

    /**
     * Default post item template
     */
    private function getDefaultPostItemTemplate(): string
    {
        return <<<'HTML'
<article class="post-item" style="margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid #eee;">
    <h2 style="margin: 0 0 0.5rem 0;"><a href="/{COLLECTION_BASE_PATH}/{SLUG}/" style="color: #333; text-decoration: none;">{TITLE}</a></h2>
    <div class="post-meta" style="font-size: 0.9rem; color: #666; margin-bottom: 0.75rem;">
        <span class="date">{DATE}</span>
        {AUTHOR}
        {CATEGORIES}
        <span class="reading-time">{READING_TIME} min read</span>
    </div>
    <p class="excerpt" style="margin: 0 0 0.75rem 0; color: #555;">{EXCERPT}</p>
    <a href="/{COLLECTION_BASE_PATH}/{SLUG}/" style="color: #007bff; text-decoration: none;">Read more →</a>
</article>
HTML;
    }

}
