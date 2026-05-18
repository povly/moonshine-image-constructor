<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Povly\MoonShineImageEditor\Enums\ImageExtension;
use Povly\MoonShineImageEditor\Support\FileSizeFormatter;

final class ImageClearConversionsCommand extends Command
{
    protected $signature = 'image-editor:clear-conversions
                            {--disk=public : Storage disk name}
                            {--path= : Limit to specific directory}
                            {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Remove orphan WebP/AVIF conversions where original image no longer exists';

    public function handle(): int
    {
        $disk = $this->option('disk');
        $pathPrefix = $this->option('path');
        $dryRun = (bool) $this->option('dry-run');

        $storage = Storage::disk($disk);
        $allFiles = $storage->allFiles();

        if ($pathPrefix) {
            $allFiles = array_filter($allFiles, fn (string $f): bool => str_starts_with($f, $pathPrefix));
        }

        $orphanCount = 0;
        $freedBytes = 0;

        foreach ($allFiles as $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if (! in_array($ext, ImageExtension::conversions(), true)) {
                continue;
            }

            $info = pathinfo($file);
            $dir = $info['dirname'];
            $baseName = $info['filename'];

            $hasOriginal = false;
            foreach (ImageExtension::sources() as $origExt) {
                if ($storage->exists("{$dir}/{$baseName}.{$origExt}")) {
                    $hasOriginal = true;

                    break;
                }
            }

            if (! $hasOriginal) {
                $size = $storage->size($file);
                $orphanCount++;
                $freedBytes += $size;

                if ($dryRun) {
                    $this->line('  Would delete: '.$file.' ('.FileSizeFormatter::format($size).')');
                } else {
                    $storage->delete($file);
                    $this->line('  Deleted: '.$file);
                }
            }
        }

        if ($orphanCount === 0) {
            $this->info('No orphan conversions found.');
        } else {
            $action = $dryRun ? 'Would delete' : 'Deleted';
            $this->info("{$action} {$orphanCount} orphan conversion(s), ".FileSizeFormatter::format($freedBytes).' freed.');
        }

        return self::SUCCESS;
    }
}
