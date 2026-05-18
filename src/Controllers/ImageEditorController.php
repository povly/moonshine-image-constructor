<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Povly\MoonShineImageEditor\Contracts\ImageOptimizerInterface;
use Povly\MoonShineImageEditor\Contracts\SettingsRepositoryInterface;
use Povly\MoonShineImageEditor\Enums\ImageExtension;
use Povly\MoonShineImageEditor\Support\OptimizationDispatcher;

class ImageEditorController extends Controller
{
    public function __construct(
        private readonly SettingsRepositoryInterface $settings,
        private readonly OptimizationDispatcher $dispatcher,
    ) {}

    public function save(Request $request): JsonResponse
    {
        $request->validate([
            'source_path' => ['required', 'string', 'max:512'],
            'image' => ['required', 'file', 'image', 'max:20480'],
            'target_format' => ['nullable', 'string', 'in:png,jpg,jpeg'],
        ]);

        $sourcePath = $request->input('source_path');
        $image = $request->file('image');
        $targetFormat = strtolower($request->input('target_format', 'png'));
        $overwrite = config('moonshine.image_editor.overwrite_original', false);

        $disk = config('moonshine.media_manager.disk', 'public');
        $storage = Storage::disk($disk);

        $normalizedSourcePath = str_replace('\\', '/', $sourcePath);

        if (str_contains($normalizedSourcePath, '..') || str_contains(urldecode($normalizedSourcePath), '..') || ! $storage->exists($normalizedSourcePath)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid source path.',
            ], 403);
        }

        $sourceInfo = pathinfo($normalizedSourcePath);
        $sourceExtension = strtolower($sourceInfo['extension'] ?? '');

        if (! in_array($sourceExtension, ImageExtension::all(), true)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid source file type.',
            ], 403);
        }

        $directory = $sourceInfo['dirname'] ?? '.';
        $filename = $sourceInfo['filename'] ?? 'image';

        $saveExtension = $targetFormat;

        if ($overwrite) {
            $saveName = $filename.'.'.$saveExtension;
        } else {
            $saveName = $filename.'-edited-'.uniqid().'.'.$saveExtension;
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
            Log::error('[ImageEditor] Failed to save image', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to save image.',
            ], 500);
        }

        $fullPath = $storage->path($saved);
        $finalPath = $saved;

        $optimizerConfig = $this->settings->getOptimizerConfig();
        $optimizer = app(ImageOptimizerInterface::class, [
            'fullPath' => $fullPath,
            'config' => $optimizerConfig,
        ]);

        $actualFullPath = $fullPath;

        if ($saveExtension !== $sourceExtension && file_exists($fullPath)) {
            $resultPath = $optimizer->convertFormat($saveExtension);

            if ($resultPath !== $fullPath) {
                $storagePath = $storage->path('');
                $finalPath = str_starts_with($resultPath, $storagePath)
                    ? ltrim(substr($resultPath, strlen($storagePath)), '/')
                    : $resultPath;
                $actualFullPath = $resultPath;
            }
        }

        $this->dispatcher->dispatch($actualFullPath, $finalPath, $disk);

        return response()->json([
            'status' => true,
            'message' => 'Image saved.',
            'path' => $finalPath,
            'url' => $storage->url($finalPath),
        ]);
    }
}
