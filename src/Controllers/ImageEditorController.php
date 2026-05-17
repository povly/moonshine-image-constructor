<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Povly\MoonShineImageEditor\Services\ImageOptimizer;
use Povly\MoonShineImageEditor\Services\SettingsService;

class ImageEditorController extends Controller
{
    public function __construct(
        private SettingsService $settingsService,
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

        // Prevent path traversal: reject paths containing directory traversal sequences
        // and verify the source file actually exists on the configured disk
        $normalizedSourcePath = str_replace('\\', '/', $sourcePath);

        if (str_contains($normalizedSourcePath, '..') || str_contains(urldecode($normalizedSourcePath), '..') || ! $storage->exists($normalizedSourcePath)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid source path.',
            ], 403);
        }

        $sourceInfo = pathinfo($normalizedSourcePath);

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];
        $sourceExtension = strtolower($sourceInfo['extension'] ?? '');

        if (! in_array($sourceExtension, $allowedExtensions, true)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid source file type.',
            ], 403);
        }
        $directory = $sourceInfo['dirname'] ?? '.';
        $filename = $sourceInfo['filename'] ?? 'image';

        // Filerobot always exports PNG regardless of user's format choice
        $originalExtension = strtolower($sourceInfo['extension'] ?? 'png');
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
            ($e);

            return response()->json([
                'status' => false,
                'message' => 'Failed to save image.',
            ], 500);
        }

        $fullPath = $storage->path($saved);
        $finalPath = $saved;

        $optimizerConfig = $this->settingsService->getOptimizerConfig();
        $optimizer = new ImageOptimizer($fullPath, $optimizerConfig);

        $actualFullPath = $fullPath;

        if ($saveExtension !== $originalExtension && file_exists($fullPath)) {
            $resultPath = $optimizer->convertFormat($saveExtension);

            if ($resultPath !== $fullPath) {
                $storagePath = $storage->path('');
                $finalPath = str_starts_with($resultPath, $storagePath)
                    ? ltrim(substr($resultPath, strlen($storagePath)), '/')
                    : $resultPath;
                $actualFullPath = $resultPath;
            }
        }

        $this->settingsService->dispatchOptimization($actualFullPath, $finalPath, $disk);

        return response()->json([
            'status' => true,
            'message' => 'Image saved.',
            'path' => $finalPath,
            'url' => $storage->url($finalPath),
        ]);
    }
}
