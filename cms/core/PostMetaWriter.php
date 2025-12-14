<?php
/**
 * PostMetaWriter - Updates metadata in blog post files
 *
 * Handles writing/updating JSON front matter in post files
 */

class PostMetaWriter
{
    private const FRONT_MATTER_START = '<?php /* POST_META';
    private const FRONT_MATTER_END = 'POST_META */ ?>';

    private string $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * Update front matter in the post file
     * Merges new metadata with existing metadata
     */
    public function updateFrontMatter(array $newMetadata): bool
    {
        if (!file_exists($this->filePath)) {
            return false;
        }

        $content = file_get_contents($this->filePath);

        // Get existing metadata if present
        $existingMetadata = $this->extractExistingMetadata($content);

        // Merge new metadata with existing
        $mergedMetadata = $this->mergeMetadata($existingMetadata, $newMetadata);

        // Generate new front matter
        $frontMatter = $this->generateFrontMatter($mergedMetadata);

        // Update the file content
        $newContent = $this->replaceFrontMatter($content, $frontMatter);

        return file_put_contents($this->filePath, $newContent) !== false;
    }

    /**
     * Extract existing metadata from content
     */
    private function extractExistingMetadata(string $content): array
    {
        $startPos = strpos($content, self::FRONT_MATTER_START);

        if ($startPos === false) {
            return [];
        }

        $jsonStart = $startPos + strlen(self::FRONT_MATTER_START);
        $endPos = strpos($content, 'POST_META */', $jsonStart);

        if ($endPos === false) {
            return [];
        }

        $jsonString = trim(substr($content, $jsonStart, $endPos - $jsonStart));

        try {
            $data = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
            return is_array($data) ? $data : [];
        } catch (JsonException $e) {
            return [];
        }
    }

    /**
     * Deep merge metadata arrays
     */
    private function mergeMetadata(array $existing, array $new): array
    {
        foreach ($new as $key => $value) {
            if (is_array($value) && isset($existing[$key]) && is_array($existing[$key])) {
                $existing[$key] = $this->mergeMetadata($existing[$key], $value);
            } else {
                $existing[$key] = $value;
            }
        }

        return $existing;
    }

    /**
     * Generate front matter string
     */
    private function generateFrontMatter(array $metadata): string
    {
        // Clean up empty values
        $metadata = $this->removeEmptyValues($metadata);

        if (empty($metadata)) {
            return '';
        }

        $json = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return self::FRONT_MATTER_START . "\n" . $json . "\n" . self::FRONT_MATTER_END . "\n";
    }

    /**
     * Replace or insert front matter in content
     */
    private function replaceFrontMatter(string $content, string $frontMatter): string
    {
        $startPos = strpos($content, self::FRONT_MATTER_START);

        if ($startPos !== false) {
            // Find the end of existing front matter
            $endPos = strpos($content, self::FRONT_MATTER_END, $startPos);

            if ($endPos !== false) {
                $endPos += strlen(self::FRONT_MATTER_END);
                // Skip any trailing newline
                if (isset($content[$endPos]) && $content[$endPos] === "\n") {
                    $endPos++;
                }

                // Replace existing front matter
                return substr($content, 0, $startPos) . $frontMatter . substr($content, $endPos);
            }
        }

        // No existing front matter, insert after opening PHP tag or at start
        if (preg_match('/^<\?php\s*\n/', $content, $matches)) {
            // Insert after <?php
            $insertPos = strlen($matches[0]);
            return substr($content, 0, $insertPos) . $frontMatter . substr($content, $insertPos);
        }

        // Insert at the very start
        return $frontMatter . $content;
    }

    /**
     * Remove empty values from array recursively
     */
    private function removeEmptyValues(array $arr): array
    {
        $result = [];

        foreach ($arr as $key => $value) {
            if (is_array($value)) {
                $cleaned = $this->removeEmptyValues($value);
                if (!empty($cleaned)) {
                    $result[$key] = $cleaned;
                }
            } elseif ($value !== '' && $value !== null && $value !== []) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
