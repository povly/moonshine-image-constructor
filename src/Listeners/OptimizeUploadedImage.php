<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor\Listeners;

use Illuminate\Support\Facades\Storage;
use Povly\MoonShineImageEditor\Services\SettingsService;
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

        app(SettingsService::class)->dispatchOptimization($fullPath, $event->path, $event->disk);
    }
}
