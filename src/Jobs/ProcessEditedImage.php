<?php

declare(strict_types=1);

namespace Povly\MoonShineImageConstructor\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Povly\MoonShineImageConstructor\Services\ImageOptimizer;

final class ProcessEditedImage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        private string $fullPath,
        private string $relativePath,
        private string $disk,
        private array $optimizerConfig,
    ) {}

    public function handle(): void
    {
        if (! file_exists($this->fullPath)) {
            return;
        }

        $optimizer = new ImageOptimizer($this->fullPath, $this->optimizerConfig);
        $optimizer->process();
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }
}
