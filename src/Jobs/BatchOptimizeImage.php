<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Povly\MoonShineImageEditor\Services\ImageOptimizer;

final class BatchOptimizeImage implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(
        private string $fullPath,
        private string $relativePath,
        private string $disk,
        private array $optimizerConfig,
        private string $trackingId,
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if (! file_exists($this->fullPath)) {
            $this->log('skipped', "File not found: {$this->relativePath}");

            return;
        }

        $sizeBefore = filesize($this->fullPath);
        $info = pathinfo($this->fullPath);
        $webpPath = $info['dirname'].'/'.$info['filename'].'.webp';
        $avifPath = $info['dirname'].'/'.$info['filename'].'.avif';
        $webpExistedBefore = file_exists($webpPath);
        $avifExistedBefore = file_exists($avifPath);
        $webpEnabled = (bool) ($this->optimizerConfig['convert']['webp']['enabled'] ?? false);
        $avifEnabled = (bool) ($this->optimizerConfig['convert']['avif']['enabled'] ?? false);

        try {
            $optimizer = new ImageOptimizer($this->fullPath, $this->optimizerConfig);
            $optimizer->process();

            $sizeAfter = file_exists($this->fullPath) ? filesize($this->fullPath) : 0;

            // Log original optimization
            $this->logSizeResult(basename($this->relativePath), $sizeBefore, $sizeAfter);

            // Log webp conversion
            if ($webpEnabled) {
                if (file_exists($webpPath)) {
                    $webpSize = filesize($webpPath);
                    $this->logConversion('webp', $info['filename'], $sizeAfter, $webpSize);
                } elseif ($webpExistedBefore) {
                    $this->log('warning', "→ {$info['filename']}.webp deleted (larger than original)");
                }
            }

            // Log avif conversion
            if ($avifEnabled) {
                if (file_exists($avifPath)) {
                    $avifSize = filesize($avifPath);
                    $compareSize = file_exists($webpPath) ? filesize($webpPath) : $sizeAfter;
                    $vsLabel = file_exists($webpPath) ? 'webp' : 'original';
                    $this->logConversion('avif', $info['filename'], $compareSize, $avifSize, $vsLabel);
                } elseif ($avifExistedBefore) {
                    $this->log('warning', "→ {$info['filename']}.avif deleted (larger than reference)");
                }
            }
        } catch (\Throwable $e) {
            $this->log('error', sprintf(
                '%s: %s',
                basename($this->relativePath),
                $e->getMessage(),
            ));

            Log::error('[ImageEditor] BatchOptimizeImage failed', [
                'path' => $this->relativePath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->log('error', sprintf(
            'FAILED: %s - %s',
            basename($this->relativePath),
            $exception->getMessage(),
        ));
    }

    private function logSizeResult(string $name, int $sizeBefore, int $sizeAfter): void
    {
        $percent = $sizeBefore > 0
            ? round((1 - $sizeAfter / $sizeBefore) * 100, 2)
            : 0;

        $this->log('success', sprintf(
            '%s (%s → %s, %s)',
            $name,
            $this->formatBytes($sizeBefore),
            $this->formatBytes($sizeAfter),
            $this->formatPercent($percent),
        ));
    }

    private function logConversion(string $format, string $baseName, int $referenceSize, int $convertedSize, string $vsLabel = 'original'): void
    {
        $percent = $referenceSize > 0
            ? round((1 - $convertedSize / $referenceSize) * 100, 2)
            : 0;

        $this->log('success', sprintf(
            '→ %s.%s (%s → %s, %s, vs %s)',
            $baseName,
            $format,
            $this->formatBytes($referenceSize),
            $this->formatBytes($convertedSize),
            $this->formatPercent($percent),
            $vsLabel,
        ));
    }

    private const MAX_LOG_ENTRIES = 500;

    private function log(string $type, string $message): void
    {
        $cacheKey = "image-editor-batch-log-{$this->trackingId}";
        $logs = Cache::get($cacheKey, []);

        $logs[] = [
            'type' => $type,
            'message' => $message,
            'time' => now()->format('H:i:s'),
        ];

        // Prevent unbounded cache growth for large batches
        if (count($logs) > self::MAX_LOG_ENTRIES) {
            $logs = array_slice($logs, -self::MAX_LOG_ENTRIES);
        }

        Cache::put($cacheKey, $logs, now()->addHours(6));
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }

    private function formatPercent(float $percent): string
    {
        if ($percent > 0) {
            return "-{$percent}%";
        }

        if ($percent < 0) {
            return '+'.abs($percent).'%';
        }

        return '0%';
    }
}
