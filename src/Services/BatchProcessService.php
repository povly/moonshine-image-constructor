<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor\Services;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Povly\MoonShineImageEditor\Jobs\BatchOptimizeImage;

final class BatchProcessService
{
    public function __construct(
        private SettingsService $settingsService,
    ) {}

    public function scanFiles(string $disk = 'public', string $filter = 'all'): array
    {
        $storage = Storage::disk($disk);
        $extensions = ['jpg', 'jpeg', 'png', 'gif'];

        $files = collect($storage->allFiles())
            ->filter(fn (string $file): bool => in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $extensions));

        if ($filter === 'only_without_conversions') {
            $files = $files->filter(function (string $file) use ($storage): bool {
                $info = pathinfo($file);

                $webpPath = $info['dirname'] . '/' . $info['filename'] . '.webp';
                $avifPath = $info['dirname'] . '/' . $info['filename'] . '.avif';

                return ! $storage->exists($webpPath) && ! $storage->exists($avifPath);
            });
        }

        return $files->values()->all();
    }

    public function startBatch(array $relativePaths, string $disk = 'public'): string
    {
        $batchId = uniqid('img_', true);
        $optimizerConfig = $this->settingsService->getOptimizerConfig();
        $storage = Storage::disk($disk);

        $jobs = [];
        foreach ($relativePaths as $relativePath) {
            $fullPath = $storage->path($relativePath);

            if (! file_exists($fullPath)) {
                continue;
            }

            $jobs[] = new BatchOptimizeImage(
                fullPath: $fullPath,
                relativePath: $relativePath,
                disk: $disk,
                optimizerConfig: $optimizerConfig,
                trackingId: $batchId,
            );
        }

        Cache::put("image-editor-batch-log-{$batchId}", [], now()->addHours(6));
        Cache::put("image-editor-batch-total-{$batchId}", count($jobs), now()->addHours(6));

        $batch = Bus::batch($jobs)
            ->name("Image Optimization: {$batchId}")
            ->onQueue(config('moonshine.image_editor.queue.queue', 'images'))
            ->dispatch();

        Cache::put("image-editor-batch-uuid-{$batchId}", $batch->id, now()->addHours(6));

        return $batchId;
    }

    public function getProgress(string $batchId): array
    {
        $total = Cache::get("image-editor-batch-total-{$batchId}", 0);
        $logs = Cache::get("image-editor-batch-log-{$batchId}", []);

        $batchUuid = Cache::get("image-editor-batch-uuid-{$batchId}");
        $batch = $batchUuid ? Bus::findBatch($batchUuid) : null;

        $processed = $batch ? $batch->processedJobs() : 0;

        return [
            'batch_id' => $batchId,
            'total' => $total,
            'processed' => $processed,
            'progress' => $total > 0 ? round(($processed / $total) * 100, 1) : 0,
            'finished' => $batch ? $batch->finished() : $processed >= $total,
            'failed' => $batch ? $batch->failedJobs : 0,
            'logs' => array_reverse($logs),
        ];
    }

    public function clearLog(string $batchId): void
    {
        Cache::forget("image-editor-batch-log-{$batchId}");
        Cache::forget("image-editor-batch-total-{$batchId}");
        Cache::forget("image-editor-batch-uuid-{$batchId}");
    }
}
