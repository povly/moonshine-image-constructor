<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor\Listeners;

use Illuminate\Support\Facades\Storage;
use Povly\MoonShineImageEditor\Enums\ImageExtension;
use Povly\MoonShineImageEditor\Support\OptimizationDispatcher;
use YuriZoom\MoonShineMediaManager\Events\MediaManagerFileUploaded;

final class OptimizeUploadedImage
{
    public function __construct(
        private readonly OptimizationDispatcher $dispatcher,
    ) {}

    public function handle(MediaManagerFileUploaded $event): void
    {
        $extension = strtolower(pathinfo($event->path, PATHINFO_EXTENSION));

        if (! in_array($extension, ImageExtension::all(), true)) {
            return;
        }

        $fullPath = Storage::disk($event->disk)->path($event->path);

        if (! file_exists($fullPath)) {
            return;
        }

        $this->dispatcher->dispatch($fullPath, $event->path, $event->disk);
    }
}
