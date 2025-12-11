<?php
/**
 * PostMetaParser - Extracts and parses metadata from blog posts
 *
 * Supports JSON front matter format:
 * <?php /* POST_META
 * {
 *   "author": {...},
 *   "categories": [...],
 *   "tags": [...]
 * }
 * POST_META *\/ ?>
 *
 * Falls back to HTML meta tags for backward compatibility
 */

class PostMetaParser
{
    private const FRONT_MATTER_START = '<?php /* POST_META';
    private const FRONT_MATTER_END = 'POST_META */';

    /**
     * Extract all metadata from a post file
     */
    public function extractMetadata(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return $this->getDefaultMetadata();
        }

        $content = file_get_contents($filePath);

        // Try to extract JSON front matter first
        $frontMatter = $this->extractFrontMatter($content);

        if ($frontMatter !== null) {
            return $this->mergeFrontMatterWithDefaults($frontMatter, $content);
        }

        // Fallback to HTML meta tags
        return $this->extractFromMetaTags($content);
    }

    /**
     * Extract JSON front matter from content
     */
    private function extractFrontMatter(string $content): ?array
    {
        $startPos = strpos($content, self::FRONT_MATTER_START);

        if ($startPos === false) {
            return null;
        }

        $startPos += strlen(self::FRONT_MATTER_START);
        $endPos = strpos($content, self::FRONT_MATTER_END, $startPos);

        if ($endPos === false) {
            return null;
        }

        $jsonString = substr($content, $startPos, $endPos - $startPos);
        $jsonString = trim($jsonString);

        try {
            $data = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
            return is_array($data) ? $data : null;
        } catch (JsonException $e) {
            error_log("Failed to parse JSON front matter: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract metadata from HTML meta tags (fallback)
     */
    private function extractFromMetaTags(string $content): array
    {
        $metadata = $this->getDefaultMetadata();

        // Extract title
        if (preg_match('/<title>(.*?)<\/title>/i', $content, $titleMatch)) {
            $title = $titleMatch[1];
            // Remove " - Blog" or similar suffix
            $title = preg_replace('/\s*-\s*.*$/', '', $title);
            $metadata['content']['title'] = $title;
        }

        // Extract meta tags
        preg_match_all('/<meta[^>]+name=["\']([^"\']+)["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $content, $metaMatches, PREG_SET_ORDER);

        foreach ($metaMatches as $match) {
            $name = strtolower($match[1]);
            $value = $match[2];

            switch ($name) {
                case 'author':
                    $metadata['author']['name'] = $value;
                    break;

                case 'date':
                case 'publish_date':
                    $metadata['dates']['published'] = $value;
                    break;

                case 'description':
                    $metadata['content']['excerpt'] = $value;
                    $metadata['seo']['description'] = $value;
                    break;

                case 'keywords':
                    $metadata['seo']['keywords'] = array_map('trim', explode(',', $value));
                    break;

                case 'categories':
                case 'category':
                    $metadata['taxonomy']['categories'] = array_map('trim', explode(',', $value));
                    break;

                case 'tags':
                    $metadata['taxonomy']['tags'] = array_map('trim', explode(',', $value));
                    break;

                case 'featured_image':
                    $metadata['media']['featured_image'] = $value;
                    break;
            }
        }

        return $metadata;
    }

    /**
     * Merge front matter data with defaults
     */
    private function mergeFrontMatterWithDefaults(array $frontMatter, string $content): array
    {
        $metadata = $this->getDefaultMetadata();

        // Merge author info
        if (isset($frontMatter['author'])) {
            if (is_string($frontMatter['author'])) {
                $metadata['author']['name'] = $frontMatter['author'];
            } elseif (is_array($frontMatter['author'])) {
                $metadata['author'] = array_merge($metadata['author'], $frontMatter['author']);
            }
        }

        // Merge dates
        if (isset($frontMatter['dates']) && is_array($frontMatter['dates'])) {
            $metadata['dates'] = array_merge($metadata['dates'], $frontMatter['dates']);
        }
        // Support legacy date fields
        if (isset($frontMatter['publish_date'])) {
            $metadata['dates']['published'] = $frontMatter['publish_date'];
        }
        if (isset($frontMatter['modified_date'])) {
            $metadata['dates']['modified'] = $frontMatter['modified_date'];
        }

        // Merge taxonomy
        if (isset($frontMatter['taxonomy']) && is_array($frontMatter['taxonomy'])) {
            $metadata['taxonomy'] = array_merge($metadata['taxonomy'], $frontMatter['taxonomy']);
        }
        // Support legacy taxonomy fields
        if (isset($frontMatter['categories'])) {
            $metadata['taxonomy']['categories'] = (array)$frontMatter['categories'];
        }
        if (isset($frontMatter['tags'])) {
            $metadata['taxonomy']['tags'] = (array)$frontMatter['tags'];
        }
        if (isset($frontMatter['series'])) {
            $metadata['taxonomy']['series'] = $frontMatter['series'];
        }

        // Merge media
        if (isset($frontMatter['media']) && is_array($frontMatter['media'])) {
            $metadata['media'] = array_merge($metadata['media'], $frontMatter['media']);
        }
        // Support legacy media fields
        if (isset($frontMatter['featured_image'])) {
            $metadata['media']['featured_image'] = $frontMatter['featured_image'];
        }

        // Merge SEO
        if (isset($frontMatter['seo']) && is_array($frontMatter['seo'])) {
            $metadata['seo'] = array_merge($metadata['seo'], $frontMatter['seo']);
        }
        // Support legacy SEO fields
        if (isset($frontMatter['meta_title'])) {
            $metadata['seo']['title'] = $frontMatter['meta_title'];
        }
        if (isset($frontMatter['meta_description'])) {
            $metadata['seo']['description'] = $frontMatter['meta_description'];
        }

        // Merge content info
        if (isset($frontMatter['content']) && is_array($frontMatter['content'])) {
            $metadata['content'] = array_merge($metadata['content'], $frontMatter['content']);
        }
        // Support legacy content fields
        if (isset($frontMatter['excerpt'])) {
            $metadata['content']['excerpt'] = $frontMatter['excerpt'];
        }
        if (isset($frontMatter['title'])) {
            $metadata['content']['title'] = $frontMatter['title'];
        }

        // Merge options
        if (isset($frontMatter['options']) && is_array($frontMatter['options'])) {
            $metadata['options'] = array_merge($metadata['options'], $frontMatter['options']);
        }
        // Support legacy option fields
        if (isset($frontMatter['featured'])) {
            $metadata['options']['featured'] = (bool)$frontMatter['featured'];
        }
        if (isset($frontMatter['allow_comments'])) {
            $metadata['options']['allow_comments'] = (bool)$frontMatter['allow_comments'];
        }

        // Merge custom fields
        if (isset($frontMatter['custom']) && is_array($frontMatter['custom'])) {
            $metadata['custom'] = array_merge($metadata['custom'], $frontMatter['custom']);
        }

        // Auto-calculate reading time and word count if not provided
        if (empty($metadata['content']['reading_time']) || empty($metadata['content']['word_count'])) {
            $stats = $this->calculateContentStats($content);
            if (empty($metadata['content']['reading_time'])) {
                $metadata['content']['reading_time'] = $stats['reading_time'];
            }
            if (empty($metadata['content']['word_count'])) {
                $metadata['content']['word_count'] = $stats['word_count'];
            }
        }

        // Extract title from content if not provided
        if (empty($metadata['content']['title'])) {
            if (preg_match('/<title>(.*?)<\/title>/i', $content, $titleMatch)) {
                $title = $titleMatch[1];
                $title = preg_replace('/\s*-\s*.*$/', '', $title);
                $metadata['content']['title'] = $title;
            }
        }

        return $metadata;
    }

    /**
     * Calculate reading time and word count
     */
    private function calculateContentStats(string $content): array
    {
        // Strip HTML tags and get text content
        $text = strip_tags($content);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        $wordCount = str_word_count($text);
        $readingTime = max(1, (int)ceil($wordCount / 200)); // 200 words per minute

        return [
            'word_count' => $wordCount,
            'reading_time' => $readingTime,
        ];
    }

    /**
     * Get default metadata structure
     */
    private function getDefaultMetadata(): array
    {
        return [
            'version' => '1.0',
            'author' => [
                'name' => '',
                'email' => '',
                'bio' => '',
                'avatar' => '',
                'social' => [],
            ],
            'dates' => [
                'published' => '',
                'modified' => '',
                'scheduled' => null,
            ],
            'taxonomy' => [
                'categories' => [],
                'tags' => [],
                'series' => null,
            ],
            'media' => [
                'featured_image' => '',
                'featured_image_alt' => '',
                'gallery' => [],
            ],
            'seo' => [
                'title' => '',
                'description' => '',
                'keywords' => [],
                'canonical' => null,
                'og_image' => '',
                'noindex' => false,
            ],
            'content' => [
                'title' => '',
                'excerpt' => '',
                'reading_time' => 0,
                'word_count' => 0,
            ],
            'options' => [
                'featured' => false,
                'allow_comments' => true,
                'template' => 'default',
                'layout' => 'default',
            ],
            'custom' => [],
        ];
    }

    /**
     * Generate JSON front matter string from metadata array
     */
    public function generateFrontMatter(array $metadata): string
    {
        // Remove default values to keep JSON clean
        $cleanMetadata = $this->removeDefaultValues($metadata);

        $json = json_encode($cleanMetadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return self::FRONT_MATTER_START . "\n" . $json . "\n" . self::FRONT_MATTER_END . " ?>\n";
    }

    /**
     * Remove default/empty values from metadata
     */
    private function removeDefaultValues(array $metadata): array
    {
        $defaults = $this->getDefaultMetadata();
        $clean = [];

        foreach ($metadata as $key => $value) {
            if (!isset($defaults[$key])) {
                $clean[$key] = $value;
                continue;
            }

            if (is_array($value)) {
                $cleanedValue = $this->removeDefaultValues($value);
                if (!empty($cleanedValue)) {
                    $clean[$key] = $cleanedValue;
                }
            } elseif ($value !== $defaults[$key] && $value !== '' && $value !== null && $value !== [] && $value !== false) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    /**
     * Check if post has front matter
     */
    public function hasFrontMatter(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $content = file_get_contents($filePath);
        return strpos($content, self::FRONT_MATTER_START) !== false;
    }

    /**
     * Helper: Get specific metadata field
     */
    public function getField(array $metadata, string $path, $default = null)
    {
        $keys = explode('.', $path);
        $value = $metadata;

        foreach ($keys as $key) {
            if (!isset($value[$key])) {
                return $default;
            }
            $value = $value[$key];
        }

        return $value;
    }
}
