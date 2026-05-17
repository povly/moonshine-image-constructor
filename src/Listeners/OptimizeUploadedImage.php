<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor\Listeners;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Povly\MoonShineImageEditor\Services\ImageOptimizer;
use YuriZoom\MoonShineMediaManager\Events\MediaManagerFileUploaded;

final class OptimizeUploadedImage
{
    public function handle(MediaManagerFileUploaded $event): void
    {
        $extension = strtolower(pathinfo($event->path, PATHINFO_EXTENSION));

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'], true)) {
            return;
        }

        $storage = Storage::disk($event->disk);
        $fullPath = $storage->path($event->path);

        if (! file_exists($fullPath)) {
            return;
        }

        $config = [
            'quality' => config('moonshine.image_editor.quality', []),
            'optimize' => config('moonshine.image_editor.optimize', []),
            'convert' => config('moonshine.image_editor.convert', []),
        ];

        if (config('moonshine.image_editor.queue.enabled', false)) {
            $connection = config('moonshine.image_editor.queue.connection');
            $queue = config('moonshine.image_editor.queue.queue', 'images');
            $delay = config('moonshine.image_editor.queue.delay');

            $job = new \Povly\MoonShineImageEditor\Jobs\ProcessEditedImage(
                $fullPath,
                $event->path,
                $event->disk,
                $config,
            );

            if ($connection) {
                $job->onConnection($connection);
            }

            $job->onQueue($queue);

            if ($delay !== null) {
                $job->delay(now()->addSeconds((int) $delay));
            }

            dispatch($job);
        } else {
            $optimizer = new ImageOptimizer($fullPath, $config);
            $optimizer->process();
        }
    }
}
