<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Povly\MoonShineImageEditor\Services\ImageOptimizer;
use Povly\MoonShineImageEditor\Services\SettingsService;

final class ImageOptimizeCommand extends Command
{
    protected $signature = 'image-editor:optimize
                            {path : Relative path of the image on the disk}
                            {--disk=public : Storage disk name}
                            {--queue : Dispatch to queue instead of processing synchronously}';

    protected $description = 'Optimize a single image and generate conversions';

    public function handle(SettingsService $settingsService): int
    {
        $path = $this->argument('path');
        $disk = $this->option('disk');

        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'], true)) {
            $this->error("Not an image file: {$path}");

            return self::FAILURE;
        }

        $fullPath = $storage->path($path);
        $optimizerConfig = $settingsService->getOptimizerConfig();

        if ($this->option('queue')) {
            $settingsService->dispatchOptimization($fullPath, $path, $disk, $optimizerConfig);
            $this->info("Queued optimization for: {$path}");
        } else {
            $optimizer = new ImageOptimizer($fullPath, $optimizerConfig);
            $optimizer->process();
            $this->info("Optimized: {$path}");
        }

        return self::SUCCESS;
    }
}
