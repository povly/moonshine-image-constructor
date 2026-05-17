<?php

declare(strict_types=1);

namespace Povly\MoonShineImageConstructor\Listeners;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Povly\MoonShineImageConstructor\Services\ImageOptimizer;
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
            'quality' => config('moonshine.image_constructor.quality', []),
            'optimize' => config('moonshine.image_constructor.optimize', []),
            'convert' => config('moonshine.image_constructor.convert', []),
        ];

        if (config('moonshine.image_constructor.queue.enabled', false)) {
            $connection = config('moonshine.image_constructor.queue.connection');
            $queue = config('moonshine.image_constructor.queue.queue', 'images');
            $delay = config('moonshine.image_constructor.queue.delay');

            $job = new \Povly\MoonShineImageConstructor\Jobs\ProcessEditedImage(
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
