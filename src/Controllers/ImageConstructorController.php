<?php

declare(strict_types=1);

namespace Povly\MoonShineImageConstructor\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Povly\MoonShineImageConstructor\Jobs\ProcessEditedImage;
use Povly\MoonShineImageConstructor\Services\ImageOptimizer;

class ImageConstructorController extends Controller
{
    public function save(Request $request): JsonResponse
    {
        $request->validate([
            'source_path' => ['required', 'string'],
            'image' => ['required', 'file', 'image'],
            'target_format' => ['nullable', 'string', 'in:png,jpg,jpeg'],
        ]);

        $sourcePath = $request->input('source_path');
        $image = $request->file('image');
        $targetFormat = strtolower($request->input('target_format', 'png'));
        $overwrite = config('moonshine.image_constructor.overwrite_original', false);

        $disk = config('moonshine.media_manager.disk', 'public');
        $storage = Storage::disk($disk);

        $sourceInfo = pathinfo($sourcePath);
        $directory = $sourceInfo['dirname'] ?? '.';
        $filename = $sourceInfo['filename'] ?? 'image';

        // Filerobot always exports PNG regardless of user's format choice
        $originalExtension = strtolower($sourceInfo['extension'] ?? 'png');
        $saveExtension = $targetFormat;

        if ($overwrite) {
            $saveName = $filename . '.' . $saveExtension;
        } else {
            $saveName = $filename . '-edited.' . $saveExtension;

            $counter = 1;
            while ($storage->exists($directory . '/' . $saveName)) {
                $saveName = $filename . '-edited-' . $counter . '.' . $saveExtension;
                $counter++;
            }
        }

        $saveDir = ($directory === '.' || $directory === '/') ? '/' : $directory;

        try {
            $saved = $storage->putFileAs($saveDir, $image, $saveName);

            if (! $saved) {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to save image.',
                ], 500);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }

        $fullPath = $storage->path($saved);
        $finalPath = $saved;

        $optimizerConfig = $this->getOptimizerConfig();
        $optimizer = new ImageOptimizer($fullPath, $optimizerConfig);

        if ($saveExtension !== $originalExtension && file_exists($fullPath)) {
            $resultPath = $optimizer->convertFormat($saveExtension);

            if ($resultPath !== $fullPath) {
                $finalPath = ltrim(str_replace($storage->path(''), '', $resultPath), '/');
            }
        }

        $this->processOptimization($fullPath, $finalPath, $disk);

        return response()->json([
            'status' => true,
            'message' => 'Image saved.',
            'path' => $finalPath,
            'url' => $storage->url($finalPath),
        ]);
    }

    private function processOptimization(string $fullPath, string $relativePath, string $disk): void
    {
        if (! file_exists($fullPath)) {
            return;
        }

        $optimizerConfig = $this->getOptimizerConfig();

        if (config('moonshine.image_constructor.queue.enabled', false)) {
            $connection = config('moonshine.image_constructor.queue.connection');
            $queue = config('moonshine.image_constructor.queue.queue', 'images');
            $delay = config('moonshine.image_constructor.queue.delay');

            $job = new ProcessEditedImage($fullPath, $relativePath, $disk, $optimizerConfig);

            if ($connection) {
                $job->onConnection($connection);
            }

            $job->onQueue($queue);

            if ($delay !== null) {
                $job->delay(now()->addSeconds((int) $delay));
            }

            dispatch($job);
        } else {
            $optimizer = new ImageOptimizer($fullPath, $optimizerConfig);
            $optimizer->process();
        }
    }

    private function getOptimizerConfig(): array
    {
        return [
            'quality' => config('moonshine.image_constructor.quality', []),
            'optimize' => config('moonshine.image_constructor.optimize', []),
            'convert' => config('moonshine.image_constructor.convert', []),
        ];
    }
}
