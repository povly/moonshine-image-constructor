<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor\Services;

use Intervention\Image\Laravel\Facades\Image;
use Illuminate\Support\Facades\Log;

final class ImageOptimizer
{
    public function __construct(
        private string $fullPath,
        private array $config = [],
    ) {}

    public function process(): void
    {
        $this->optimize();

        if ($this->config['convert']['webp']['enabled'] ?? false) {
            $this->convertWebp();
        }

        if ($this->config['convert']['avif']['enabled'] ?? false) {
            $this->convertAvif();
        }
    }

    public function convertFormat(string $targetFormat): string
    {
        $sourceFormat = strtolower(pathinfo($this->fullPath, PATHINFO_EXTENSION));

        if ($sourceFormat === $targetFormat) {
            return $this->fullPath;
        }

        $image = Image::read($this->fullPath);

        $targetPath = $this->getConvertedPath($this->fullPath, $targetFormat);
        $quality = $this->config['quality'][$targetFormat] ?? 82;

        match ($targetFormat) {
            'jpg', 'jpeg' => $image->toJpeg(
                quality: $quality,
                progressive: true,
                strip: $this->config['optimize']['strip_metadata'] ?? true,
            )->save($targetPath),
            'png' => $image->toPng()->save($targetPath),
            default => null,
        };

        if (! file_exists($targetPath)) {
            return $this->fullPath;
        }

        if (filesize($targetPath) >= filesize($this->fullPath)) {
            unlink($targetPath);

            return $this->fullPath;
        }

        if ($sourceFormat !== $targetFormat && file_exists($this->fullPath) && $this->fullPath !== $targetPath) {
            unlink($this->fullPath);
        }

        return $targetPath;
    }

    public function optimize(): void
    {
        if (! ($this->config['optimize']['enabled'] ?? true)) {
            return;
        }

        $sizeBefore = filesize($this->fullPath);

        $image = Image::read($this->fullPath);

        $maxWidth = $this->config['optimize']['max_width'] ?? null;
        $maxHeight = $this->config['optimize']['max_height'] ?? null;

        if ($maxWidth !== null || $maxHeight !== null) {
            $image = $image->scaleDown(width: $maxWidth, height: $maxHeight);
        }

        $extension = strtolower(pathinfo($this->fullPath, PATHINFO_EXTENSION));
        $stripMetadata = $this->config['optimize']['strip_metadata'] ?? true;
        $quality = $this->config['quality'][$extension] ?? 85;

        $encoded = match ($extension) {
            'jpg', 'jpeg' => $image->toJpeg(
                quality: $quality,
                progressive: true,
                strip: $stripMetadata,
            ),
            'png' => $image->toPng(),
            default => null,
        };

        if ($encoded === null) {
            return;
        }

        $tempPath = $this->fullPath . '.tmp';
        $encoded->save($tempPath);

        if (! file_exists($tempPath)) {
            Log::warning('[ImageEditor] optimize: temp file not created', [
                'path' => $this->fullPath,
                'extension' => $extension,
            ]);

            return;
        }

        $sizeAfter = filesize($tempPath);

        Log::info('[ImageEditor] optimize', [
            'path' => basename($this->fullPath),
            'extension' => $extension,
            'before_bytes' => $sizeBefore,
            'after_bytes' => $sizeAfter,
            'saved_percent' => round((1 - $sizeAfter / $sizeBefore) * 100, 2),
        ]);

        if ($sizeAfter < $sizeBefore) {
            rename($tempPath, $this->fullPath);
        } else {
            unlink($tempPath);
        }
    }

    public function convertWebp(): void
    {
        $webpPath = $this->getConvertedPath($this->fullPath, 'webp');
        $quality = $this->config['convert']['webp']['quality'] ?? 80;

        try {
            $image = Image::read($this->fullPath);
            $image->toWebp(quality: $quality)->save($webpPath);

            if (! file_exists($webpPath)) {
                return;
            }

            $originalSize = filesize($this->fullPath);

            if (filesize($webpPath) >= $originalSize) {
                unlink($webpPath);
            }
        } catch (\Throwable $e) {
            if (file_exists($webpPath)) {
                unlink($webpPath);
            }
        }
    }

    public function convertAvif(): void
    {
        $avifPath = $this->getConvertedPath($this->fullPath, 'avif');
        $quality = $this->config['convert']['avif']['quality'] ?? 65;

        try {
            $image = Image::read($this->fullPath);
            $image->toAvif(quality: $quality)->save($avifPath);

            if (! file_exists($avifPath)) {
                return;
            }

            $webpPath = $this->getConvertedPath($this->fullPath, 'webp');

            if (file_exists($webpPath)) {
                $comparisonSize = filesize($webpPath);
            } else {
                $comparisonSize = filesize($this->fullPath);
            }

            if (filesize($avifPath) >= $comparisonSize) {
                unlink($avifPath);
            }
        } catch (\Throwable $e) {
            if (file_exists($avifPath)) {
                unlink($avifPath);
            }
        }
    }

    private function getConvertedPath(string $originalPath, string $format): string
    {
        $info = pathinfo($originalPath);

        return $info['dirname'] . '/' . $info['filename'] . '.' . $format;
    }
}
