<?php
/**
 * BlockParser - Parses and updates CMS blocks in PHP template files.
 *
 * Blocks are defined via PHP comment markers:
 * <?php /* CMS:BLOCK name=header role=meta custom=1 start *\/ ?>
 * ... content ...
 * <?php /* CMS:BLOCK name=header end *\/ ?>
 */

class BlockParser
{
    /**
     * Parse all blocks from a template file.
     *
     * @param string $filePath Absolute path to the PHP template file
     * @return array Array of blocks with metadata and content
     * @throws Exception if file cannot be read
     */
    public function parseBlocks(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: {$filePath}");
        }

        if (!is_readable($filePath)) {
            throw new Exception("File not readable: {$filePath}");
        }

        $content = file_get_contents($filePath);
        $blocks = [];

        // Regex to match block start markers
        $pattern = '/<!--\s*CMS:BLOCK\s+(.+?)\s+start\s*-->|<\?php\s+\/\*\s*CMS:BLOCK\s+(.+?)\s+start\s*\*\/\s*\?>/i';

        preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $index => $match) {
            $startTag = $match[0];
            $startPos = $match[1];

            // Extract attributes from start tag
            $attrString = $matches[1][$index][0] ?: $matches[2][$index][0];
            $attributes = $this->parseAttributes($attrString);

            if (!isset($attributes['name'])) {
                continue; // Skip blocks without name
            }

            $blockName = $attributes['name'];

            // Find corresponding end tag
            $endPattern = '/<!--\s*CMS:BLOCK\s+name=' . preg_quote($blockName, '/') . '\s+end\s*-->|<\?php\s+\/\*\s*CMS:BLOCK\s+name=' . preg_quote($blockName, '/') . '\s+end\s*\*\/\s*\?>/i';

            if (preg_match($endPattern, $content, $endMatch, PREG_OFFSET_CAPTURE, $startPos)) {
                $endTag = $endMatch[0][0];
                $endPos = $endMatch[0][1];

                // Extract content between tags
                $contentStart = $startPos + strlen($startTag);
                $contentLength = $endPos - $contentStart;
                $blockContent = substr($content, $contentStart, $contentLength);

                $blocks[] = [
                    'name' => $blockName,
                    'role' => $attributes['role'] ?? null,
                    'custom' => isset($attributes['custom']) && $attributes['custom'] === '1',
                    'system' => isset($attributes['system']) && $attributes['system'] === '1',
                    'content' => $blockContent,
                    'start_pos' => $startPos,
                    'end_pos' => $endPos + strlen($endTag),
                ];
            }
        }

        return $blocks;
    }

    /**
     * Update a single block's content in a file.
     *
     * @param string $filePath Absolute path to the PHP template file
     * @param string $blockName Name of the block to update
     * @param string $newContent New content for the block
     * @param bool|null $customFlag If provided, sets or removes the custom=1 attribute
     * @return void
     * @throws Exception if block not found or file cannot be written
     */
    public function updateBlock(string $filePath, string $blockName, string $newContent, ?bool $customFlag = null): void
    {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: {$filePath}");
        }

        if (!is_readable($filePath) || !is_writable($filePath)) {
            throw new Exception("File not readable/writable: {$filePath}");
        }

        $content = file_get_contents($filePath);
        $blocks = $this->parseBlocks($filePath);

        // Find the block to update
        $targetBlock = null;
        foreach ($blocks as $block) {
            if ($block['name'] === $blockName) {
                $targetBlock = $block;
                break;
            }
        }

        if (!$targetBlock) {
            throw new Exception("Block '{$blockName}' not found in file");
        }

        // Reconstruct the start tag with potentially updated custom flag
        $startPattern = '/(<\?php\s+\/\*\s*CMS:BLOCK\s+)(.+?)(\s+start\s*\*\/\s*\?>|<!--\s*CMS:BLOCK\s+)(.+?)(\s+start\s*-->)/i';

        // Find and replace the block content
        $beforeBlock = substr($content, 0, $targetBlock['start_pos']);
        $afterBlock = substr($content, $targetBlock['end_pos']);

        // Build new start tag
        $attributes = ['name' => $blockName];
        if ($targetBlock['role']) {
            $attributes['role'] = $targetBlock['role'];
        }
        if ($customFlag !== null) {
            if ($customFlag) {
                $attributes['custom'] = '1';
            }
        } else {
            if ($targetBlock['custom']) {
                $attributes['custom'] = '1';
            }
        }

        $attrString = $this->buildAttributeString($attributes);

        // Determine comment style from original
        $originalStart = substr($content, $targetBlock['start_pos'], 10);
        if (strpos($originalStart, '<?php') !== false) {
            $newStartTag = "<?php /* CMS:BLOCK {$attrString} start */ ?>";
            $newEndTag = "<?php /* CMS:BLOCK name={$blockName} end */ ?>";
        } else {
            $newStartTag = "<!-- CMS:BLOCK {$attrString} start -->";
            $newEndTag = "<!-- CMS:BLOCK name={$blockName} end -->";
        }

        // Reconstruct file content
        $newFileContent = $beforeBlock . $newStartTag . $newContent . $newEndTag . $afterBlock;

        // Write back to file
        if (file_put_contents($filePath, $newFileContent) === false) {
            throw new Exception("Failed to write file: {$filePath}");
        }
    }

    /**
     * Parse attributes from a block start tag attribute string.
     *
     * @param string $attrString e.g., "name=header role=meta custom=1"
     * @return array Associative array of attributes
     */
    private function parseAttributes(string $attrString): array
    {
        $attributes = [];

        // Match attribute pairs: name=value
        preg_match_all('/(\w+)=([^\s]+)/', $attrString, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $attributes[$match[1]] = $match[2];
        }

        return $attributes;
    }

    /**
     * Build an attribute string from an associative array.
     *
     * @param array $attributes Associative array of attributes
     * @return string e.g., "name=header role=meta custom=1"
     */
    private function buildAttributeString(array $attributes): string
    {
        $parts = [];
        foreach ($attributes as $key => $value) {
            $parts[] = "{$key}={$value}";
        }
        return implode(' ', $parts);
    }
}
