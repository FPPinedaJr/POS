<?php

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;

class ImageService
{
    private int $maxFileSize;
    private array $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    private string $basePath;
    private string $baseUrl;
    private ImageManager $images;

    public function __construct(
        string $basePath,
        string $baseUrl,
        int $maxFileSize = 5_000_000
    ) {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->maxFileSize = $maxFileSize;

        // Pick a working Intervention Image v3 driver
        if (extension_loaded('gd')) {
            $driver = new GdDriver();
        } elseif (extension_loaded('imagick')) {
            $driver = new ImagickDriver();
        } else {
            throw new RuntimeException(
                'No supported image driver found. Enable GD or Imagick extension in PHP.'
            );
        }

        $this->images = new ImageManager($driver);
    }

    public function handleUpload(array $file): array
    {
        $this->validateUploadArray($file);
        $this->validateFileSize($file);
        $mime = $this->detectMimeType($file['tmp_name']);
        $this->validateMimeType($mime);

        $thumbDir = $this->basePath . DIRECTORY_SEPARATOR . 'thumbs';
        $previewDir = $this->basePath . DIRECTORY_SEPARATOR . 'previews';
        $originalDir = $this->basePath . DIRECTORY_SEPARATOR . 'originals';

        $this->ensureDirectory($thumbDir);
        $this->ensureDirectory($previewDir);
        $this->ensureDirectory($originalDir);

        $baseName = uniqid('img_', true);
        $thumbFilename = $baseName . '_thumb.webp';
        $previewFilename = $baseName . '_preview.webp';
        $originalFilename = $baseName . '_original' . $this->guessExtensionFromMime($mime);

        $thumbPath = $thumbDir . DIRECTORY_SEPARATOR . $thumbFilename;
        $previewPath = $previewDir . DIRECTORY_SEPARATOR . $previewFilename;
        $originalPath = $originalDir . DIRECTORY_SEPARATOR . $originalFilename;

        if (!move_uploaded_file($file['tmp_name'], $originalPath)) {
            throw new RuntimeException('Failed to move uploaded file.');
        }

        // ==========================================
        // FIX: Retain Aspect Ratio for Thumbnail
        // ==========================================
        $imageForThumb = $this->images->read($originalPath);
        $imageForThumb
            ->scaleDown(300, 300) // Changed from cover(300, 300)
            ->encodeByExtension('webp', quality: 80)
            ->save($thumbPath);

        // ==========================================
        // FIX: Retain Aspect Ratio for Preview
        // ==========================================
        $imageForPreview = $this->images->read($originalPath);
        $imageForPreview
            ->scaleDown(800, 800) // Updated to scaleDown for consistency
            ->encodeByExtension('webp', quality: 80)
            ->save($previewPath);

        // Calculate final dimensions for accurate HTML rendering
        $thumbWidth = $imageForThumb->width();
        $thumbHeight = $imageForThumb->height();

        $previewWidth = $imageForPreview->width();
        $previewHeight = $imageForPreview->height();

        return [
            'original' => [
                'path' => $originalPath,
                'url' => $this->baseUrl . '/originals/' . $originalFilename,
                'mime' => $mime,
            ],
            'thumb' => [
                'path' => $thumbPath,
                'url' => $this->baseUrl . '/thumbs/' . $thumbFilename,
                'width' => $thumbWidth, // Now accurately reflects the aspect ratio
                'height' => $thumbHeight, // Now accurately reflects the aspect ratio
                'mime' => 'image/webp',
            ],
            'preview' => [
                'path' => $previewPath,
                'url' => $this->baseUrl . '/previews/' . $previewFilename,
                'width' => $previewWidth,
                'height' => $previewHeight,
                'mime' => 'image/webp',
            ],
            'basename' => $baseName,
        ];
    }

    public function renderImgTag(array $imageMeta, string $alt = '', array $attrs = []): string
    {
        $thumb = $imageMeta['thumb'] ?? null;
        $preview = $imageMeta['preview'] ?? null;

        if (!$thumb || !$preview) {
            throw new RuntimeException('Invalid image metadata passed to renderImgTag.');
        }

        $attr = [
            'src' => $thumb['url'],
            'alt' => $alt,
            'loading' => 'lazy',
            'width' => (string) $thumb['width'],
            'height' => (string) $thumb['height'],
            'decoding' => 'async',
            'srcset' => sprintf('%s %dw, %s %dw', $thumb['url'], $thumb['width'], $preview['url'], $preview['width']), // Fixed to use actual dynamic widths
            'sizes' => '(max-width: 640px) 50vw, (max-width: 1024px) 25vw, 200px',
        ];

        foreach ($attrs as $key => $value) {
            $attr[$key] = $value;
        }

        $parts = [];
        foreach ($attr as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $parts[] = htmlspecialchars($key, ENT_QUOTES) . '="' . htmlspecialchars($value, ENT_QUOTES) . '"';
        }

        return '<img ' . implode(' ', $parts) . '>';
    }

    private function validateUploadArray(array $file): void
    {
        if (!isset($file['error'], $file['tmp_name'], $file['size'])) {
            throw new RuntimeException('Invalid upload array.');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload error code: ' . $file['error']);
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Possible file upload attack.');
        }
    }

    private function validateFileSize(array $file): void
    {
        if ($file['size'] > $this->maxFileSize) {
            throw new RuntimeException('File too large. Max allowed is ' . $this->maxFileSize . ' bytes.');
        }
    }

    private function detectMimeType(string $tmpPath): string
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath) ?: 'application/octet-stream';

        return $mime;
    }

    private function validateMimeType(string $mime): void
    {
        if (!in_array($mime, $this->allowedMimeTypes, true)) {
            throw new RuntimeException('Unsupported file type: ' . $mime);
        }
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            if (!mkdir($path, 0775, true) && !is_dir($path)) {
                throw new RuntimeException('Failed to create directory: ' . $path);
            }
        }
    }

    private function guessExtensionFromMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/gif' => '.gif',
            'image/webp' => '.webp',
            default => '',
        };
    }
}