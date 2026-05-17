<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Povly\MoonShineImageEditor\Services\BatchProcessService;

final class BatchProcessController extends Controller
{
    private const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];

    private const MAX_BATCH_FILES = 500;

    public function __construct(
        private BatchProcessService $batchService,
    ) {}

    public function scan(Request $request): JsonResponse
    {
        $disk = config('moonshine.media_manager.disk', 'public');
        $filter = $request->input('filter', 'all');
        $files = $this->batchService->scanFiles($disk, $filter);

        return response()->json([
            'files' => $files,
            'count' => count($files),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $files = $request->input('files', []);

        if (empty($files)) {
            return response()->json([
                'status' => false,
                'message' => __('image-editor::image-editor.no_files_selected'),
            ], 422);
        }

        $files = array_slice($files, 0, self::MAX_BATCH_FILES);

        $files = array_values(array_filter($files, function (string $path): bool {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            return in_array($ext, self::ALLOWED_IMAGE_EXTENSIONS, true);
        }));

        $disk = config('moonshine.media_manager.disk', 'public');

        $batchId = $this->batchService->startBatch($files, $disk);

        return response()->json([
            'status' => true,
            'batch_id' => $batchId,
            'total' => count($files),
        ]);
    }

    public function progress(Request $request): JsonResponse
    {
        $batchId = $request->input('batch_id');

        if (! $batchId) {
            return response()->json([
                'status' => false,
                'message' => 'batch_id is required',
            ], 422);
        }

        return response()->json(
            $this->batchService->getProgress($batchId),
        );
    }

    public function clearLog(Request $request): JsonResponse
    {
        $batchId = $request->input('batch_id');

        if ($batchId) {
            $this->batchService->clearLog($batchId);
        }

        return response()->json([
            'status' => true,
        ]);
    }
}
