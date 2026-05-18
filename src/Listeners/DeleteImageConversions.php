<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor\Listeners;

use Illuminate\Support\Facades\Storage;
use Povly\MoonShineImageEditor\Enums\ImageExtension;
use YuriZoom\MoonShineMediaManager\Events\MediaManagerFileDeleted;

final class DeleteImageConversions
{
    public function handle(MediaManagerFileDeleted $event): void
    {
        $extension = strtolower(pathinfo($event->path, PATHINFO_EXTENSION));

        if (! in_array($extension, ImageExtension::all(), true)) {
            return;
        }

        $storage = Storage::disk($event->disk);
        $info = pathinfo($event->path);
        $basePath = $info['dirname'].'/'.$info['filename'];

        foreach (ImageExtension::conversions() as $format) {
            $conversionPath = $basePath.'.'.$format;

            if ($storage->exists($conversionPath)) {
                $storage->delete($conversionPath);
            }
        }
    }
}
