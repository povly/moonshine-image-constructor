<?php

declare(strict_types=1);

namespace YuriZoom\MoonShineImageConstructor\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageConstructorController extends Controller
{
    public function save(Request $request): JsonResponse
    {
        $request->validate([
            'source_path' => ['required', 'string'],
            'image' => ['required', 'file', 'image'],
        ]);

        $sourcePath = $request->input('source_path');
        $image = $request->file('image');
        $overwrite = config('moonshine.image_constructor.overwrite_original', false);

        $disk = config('moonshine.media_manager.disk', 'public');
        $storage = Storage::disk($disk);

        $info = pathinfo($sourcePath);
        $directory = $info['dirname'] ?? '.';
        $filename = $info['filename'] ?? 'image';
        $extension = $image->getClientOriginalExtension() ?: ($info['extension'] ?? 'png');

        if ($overwrite) {
            $saveName = $filename.'.'.$extension;
        } else {
            $saveName = $filename.'-edited.'.$extension;

            $counter = 1;
            while ($storage->exists($directory.'/'.$saveName)) {
                $saveName = $filename.'-edited-'.$counter.'.'.$extension;
                $counter++;
            }
        }

        $saveDir = ($directory === '.' || $directory === '/') ? '/' : $directory;

        try {
            $saved = $storage->putFileAs(
                $saveDir,
                $image,
                $saveName,
            );

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

        return response()->json([
            'status' => true,
            'message' => 'Image saved.',
            'path' => $saved,
            'url' => $storage->url($saved),
        ]);
    }
}
