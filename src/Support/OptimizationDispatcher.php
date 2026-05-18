<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor\Support;

use Povly\MoonShineImageEditor\Contracts\ImageOptimizerInterface;
use Povly\MoonShineImageEditor\Contracts\SettingsRepositoryInterface;
use Povly\MoonShineImageEditor\Jobs\ProcessEditedImage;

final class OptimizationDispatcher
{
    /**
     * Dispatch image optimization — sync or queued based on config.
     */
    public function dispatch(string $fullPath, string $relativePath, string $disk, ?array $optimizerConfig = null): void
    {
        $optimizerConfig ??= $this->getOptimizerConfig();

        if (config('moonshine.image_editor.queue.enabled', false)) {
            $this->dispatchToQueue($fullPath, $relativePath, $disk, $optimizerConfig);
        } else {
            $this->processSync($fullPath, $optimizerConfig);
        }
    }

    private function processSync(string $fullPath, array $optimizerConfig): void
    {
        $optimizer = $this->createOptimizer($fullPath, $optimizerConfig);
        $optimizer->process();
    }

    private function dispatchToQueue(string $fullPath, string $relativePath, string $disk, array $optimizerConfig): void
    {
        $connection = config('moonshine.image_editor.queue.connection');
        $queue = config('moonshine.image_editor.queue.queue', 'images');
        $delay = config('moonshine.image_editor.queue.delay');

        $job = new ProcessEditedImage($fullPath, $relativePath, $disk, $optimizerConfig);

        if ($connection) {
            $job->onConnection($connection);
        }

        $job->onQueue($queue);

        if ($delay !== null) {
            $job->delay(now()->addSeconds((int) $delay));
        }

        dispatch($job);
    }

    private function createOptimizer(string $fullPath, array $config): ImageOptimizerInterface
    {
        return app(ImageOptimizerInterface::class, [
            'fullPath' => $fullPath,
            'config' => $config,
        ]);
    }

    private function getOptimizerConfig(): array
    {
        return app(SettingsRepositoryInterface::class)->getOptimizerConfig();
    }
}
