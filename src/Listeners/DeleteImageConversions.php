<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor\Listeners;

use Illuminate\Support\Facades\Storage;
use YuriZoom\MoonShineMediaManager\Events\MediaManagerFileDeleted;

final class DeleteImageConversions
{
    public function handle(MediaManagerFileDeleted $event): void
    {
        $extension = strtolower(pathinfo($event->path, PATHINFO_EXTENSION));

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'], true)) {
            return;
        }

        $storage = Storage::disk($event->disk);
        $info = pathinfo($event->path);
        $basePath = $info['dirname'].'/'.$info['filename'];

        foreach (['webp', 'avif'] as $format) {
            $conversionPath = $basePath.'.'.$format;

            if ($storage->exists($conversionPath)) {
                $storage->delete($conversionPath);
            }
        }
    }
}
