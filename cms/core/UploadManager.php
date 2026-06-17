<?php
/**
 * Upload Manager
 * Handles file and image uploads with automatic resizing and format conversion
 */

class UploadManager
{
    private string $rootDir;
    private string $uploadsDir;
    private int $imageThumbnailWidth;
    private int $imageThumbnailHeight;
    private int $imageFullWidth;
    private int $imageFullHeight;

    public function __construct(
        string $rootDir,
        string $uploadsDir,
        int $imageThumbnailWidth = 300,
        int $imageThumbnailHeight = 300,
        int $imageFullWidth = 1920,
        int $imageFullHeight = 1080
    ) {
        $this->rootDir = rtrim($rootDir, '/');
        $this->uploadsDir = trim($uploadsDir, '/');
        $this->imageThumbnailWidth = $imageThumbnailWidth;
        $this->imageThumbnailHeight = $imageThumbnailHeight;
        $this->imageFullWidth = $imageFullWidth;
        $this->imageFullHeight = $imageFullHeight;

        // Ensure uploads directory exists
        $fullUploadPath = $this->rootDir . '/' . $this->uploadsDir;
        if (!is_dir($fullUploadPath)) {
            mkdir($fullUploadPath, 0755, true);
        }
    }

    /**
     * Upload a regular file
     *
     * @param string $base64Data Base64 encoded file data
     * @param string $filename Original filename
     * @param string|null $subdir Optional subdirectory within uploads
     * @return array Upload result with 'success', 'url', 'path', 'filename'
     */
    public function uploadFile(string $base64Data, string $filename, ?string $subdir = null): array
    {
        try {
            // Decode base64 data
            $fileData = base64_decode($base64Data);
            if ($fileData === false) {
                throw new Exception('Invalid base64 data');
            }

            // Get original extension
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            // Generate unique hash-based filename
            $hash = bin2hex(random_bytes(16));
            $safeFilename = $hash . '.' . $extension;

            // Ensure filename is unique (though hash collision is virtually impossible)
            $finalFilename = $this->getUniqueFilename($safeFilename, $subdir);

            // Build full path
            $relativePath = $this->uploadsDir;
            if ($subdir) {
                $relativePath .= '/' . trim($subdir, '/');
            }
            $relativePath .= '/' . $finalFilename;

            $fullPath = $this->rootDir . '/' . $relativePath;

            // Ensure directory exists
            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // Write file
            if (file_put_contents($fullPath, $fileData) === false) {
                throw new Exception('Failed to write file');
            }

            return [
                'success' => true,
                'url' => '/' . $relativePath,
                'path' => $relativePath,
                'filename' => $finalFilename
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload and process an image (resize, convert to WebP and PNG)
     *
     * @param string $base64Data Base64 encoded image data
     * @param string $filename Original filename
     * @param string|null $subdir Optional subdirectory within uploads
     * @return array Upload result with URLs for full and thumbnail images in both formats
     */
    public function uploadImage(string $base64Data, string $filename, ?string $subdir = null, bool $includeWebp = false): array
    {
        try {
            // Decode base64 data
            $imageData = base64_decode($base64Data);
            if ($imageData === false) {
                throw new Exception('Invalid base64 data');
            }

            // Create image from string
            $sourceImage = @imagecreatefromstring($imageData);
            if ($sourceImage === false) {
                throw new Exception('Invalid image data');
            }

            // Get original dimensions
            $originalWidth = imagesx($sourceImage);
            $originalHeight = imagesy($sourceImage);

            // Generate unique hash-based filename (no extension yet)
            $hash = bin2hex(random_bytes(16));

            // Create unique base filename (ensure uniqueness though collision is virtually impossible)
            $baseFilename = $this->getUniqueFilename($hash, $subdir, false);

            // Build directory path
            $relativePath = $this->uploadsDir;
            if ($subdir) {
                $relativePath .= '/' . trim($subdir, '/');
            }

            $fullDir = $this->rootDir . '/' . $relativePath;
            if (!is_dir($fullDir)) {
                mkdir($fullDir, 0755, true);
            }

            $result = [
                'success' => true,
                'original_width' => $originalWidth,
                'original_height' => $originalHeight,
                'full' => [],
                'thumbnail' => []
            ];

            // Generate full-size images
            $fullDimensions = $this->calculateDimensions(
                $originalWidth,
                $originalHeight,
                $this->imageFullWidth,
                $this->imageFullHeight
            );

            $fullImage = $this->resizeImage($sourceImage, $originalWidth, $originalHeight, $fullDimensions['width'], $fullDimensions['height']);

            // Save PNG full (default format). PNG uses max lossless compression (9).
            $fullPngFilename = $baseFilename . '.png';
            $fullPngPath = $fullDir . '/' . $fullPngFilename;
            imagepng($fullImage, $fullPngPath, 9);
            if (!file_exists($fullPngPath) || filesize($fullPngPath) === 0) {
                throw new Exception('Failed to write image — check that the uploads directory is writable: ' . $fullDir);
            }
            $result['full']['png'] = [
                'url' => '/' . $relativePath . '/' . $fullPngFilename,
                'path' => $relativePath . '/' . $fullPngFilename,
                'width' => $fullDimensions['width'],
                'height' => $fullDimensions['height']
            ];

            // WebP is only generated on request (PNG is the default).
            if ($includeWebp) {
                $fullWebpFilename = $baseFilename . '.webp';
                $fullWebpPath = $fullDir . '/' . $fullWebpFilename;
                imagewebp($fullImage, $fullWebpPath, 85);
                $result['full']['webp'] = [
                    'url' => '/' . $relativePath . '/' . $fullWebpFilename,
                    'path' => $relativePath . '/' . $fullWebpFilename,
                    'width' => $fullDimensions['width'],
                    'height' => $fullDimensions['height']
                ];
            }

            imagedestroy($fullImage);

            // Generate thumbnail images
            $thumbDimensions = $this->calculateDimensions(
                $originalWidth,
                $originalHeight,
                $this->imageThumbnailWidth,
                $this->imageThumbnailHeight
            );

            $thumbImage = $this->resizeImage($sourceImage, $originalWidth, $originalHeight, $thumbDimensions['width'], $thumbDimensions['height']);

            // Save PNG thumbnail (default format)
            $thumbPngFilename = $baseFilename . '-thumb.png';
            $thumbPngPath = $fullDir . '/' . $thumbPngFilename;
            imagepng($thumbImage, $thumbPngPath, 9);

            // WebP thumbnail only on request
            if ($includeWebp) {
                $thumbWebpFilename = $baseFilename . '-thumb.webp';
                $thumbWebpPath = $fullDir . '/' . $thumbWebpFilename;
                imagewebp($thumbImage, $thumbWebpPath, 85);
                $result['thumbnail']['webp'] = [
                    'url' => '/' . $relativePath . '/' . $thumbWebpFilename,
                    'path' => $relativePath . '/' . $thumbWebpFilename,
                    'width' => $thumbDimensions['width'],
                    'height' => $thumbDimensions['height']
                ];
            }

            $result['thumbnail']['png'] = [
                'url' => '/' . $relativePath . '/' . $thumbPngFilename,
                'path' => $relativePath . '/' . $thumbPngFilename,
                'width' => $thumbDimensions['width'],
                'height' => $thumbDimensions['height']
            ];

            imagedestroy($thumbImage);
            imagedestroy($sourceImage);

            return $result;

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Calculate dimensions to fit within max width/height while maintaining aspect ratio
     */
    private function calculateDimensions(int $originalWidth, int $originalHeight, int $maxWidth, int $maxHeight): array
    {
        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);

        // If image is smaller than max dimensions, don't upscale
        if ($ratio > 1) {
            return [
                'width' => $originalWidth,
                'height' => $originalHeight
            ];
        }

        return [
            'width' => (int)round($originalWidth * $ratio),
            'height' => (int)round($originalHeight * $ratio)
        ];
    }

    /**
     * Resize image using GD
     */
    private function resizeImage($sourceImage, int $sourceWidth, int $sourceHeight, int $targetWidth, int $targetHeight)
    {
        $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);

        // Preserve transparency for PNG
        imagealphablending($targetImage, false);
        imagesavealpha($targetImage, true);

        imagecopyresampled(
            $targetImage,
            $sourceImage,
            0, 0, 0, 0,
            $targetWidth, $targetHeight,
            $sourceWidth, $sourceHeight
        );

        return $targetImage;
    }

    /**
     * Sanitize filename to prevent directory traversal and other issues
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove any path components
        $filename = basename($filename);

        // Remove any special characters except alphanumeric, dash, underscore, and dot
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        // Remove multiple consecutive underscores
        $filename = preg_replace('/_+/', '_', $filename);

        // Remove leading/trailing underscores
        $filename = trim($filename, '_');

        return $filename;
    }

    /**
     * Get unique filename if file already exists
     */
    private function getUniqueFilename(string $filename, ?string $subdir, bool $includeExtension = true): string
    {
        $dir = $this->rootDir . '/' . $this->uploadsDir;
        if ($subdir) {
            $dir .= '/' . trim($subdir, '/');
        }

        if ($includeExtension) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $basename = pathinfo($filename, PATHINFO_FILENAME);
        } else {
            $basename = $filename;
            $extension = '';
        }

        $counter = 1;
        $finalFilename = $filename;

        while (file_exists($dir . '/' . $finalFilename)) {
            if ($includeExtension && $extension) {
                $finalFilename = $basename . '-' . $counter . '.' . $extension;
            } else {
                $finalFilename = $basename . '-' . $counter;
            }
            $counter++;
        }

        return $finalFilename;
    }
}
