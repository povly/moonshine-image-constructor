<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Povly\MoonShineImageEditor\Services\ImageOptimizer;
use Povly\MoonShineImageEditor\Support\BatchLogReporter;

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
        private readonly string $fullPath,
        private readonly string $relativePath,
        private readonly string $disk,
        private readonly array $optimizerConfig,
        private readonly string $trackingId,
    ) {}

    public function handle(): void
    {
        $reporter = new BatchLogReporter($this->trackingId);

        if ($this->batch()?->cancelled()) {
            return;
        }

        if (! file_exists($this->fullPath)) {
            $reporter->log('skipped', "File not found: {$this->relativePath}");

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

            $reporter->logSizeResult(basename($this->relativePath), $sizeBefore, $sizeAfter);

            if ($webpEnabled) {
                if (file_exists($webpPath)) {
                    $reporter->logConversion('webp', $info['filename'], $sizeAfter, filesize($webpPath));
                } elseif ($webpExistedBefore) {
                    $reporter->log('warning', "→ {$info['filename']}.webp deleted (larger than original)");
                }
            }

            if ($avifEnabled) {
                if (file_exists($avifPath)) {
                    $compareSize = file_exists($webpPath) ? filesize($webpPath) : $sizeAfter;
                    $vsLabel = file_exists($webpPath) ? 'webp' : 'original';
                    $reporter->logConversion('avif', $info['filename'], $compareSize, filesize($avifPath), $vsLabel);
                } elseif ($avifExistedBefore) {
                    $reporter->log('warning', "→ {$info['filename']}.avif deleted (larger than reference)");
                }
            }
        } catch (\Throwable $e) {
            $reporter->log('error', sprintf(
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
        $reporter = new BatchLogReporter($this->trackingId);
        $reporter->log('error', sprintf(
            'FAILED: %s - %s',
            basename($this->relativePath),
            $exception->getMessage(),
        ));
    }
}
