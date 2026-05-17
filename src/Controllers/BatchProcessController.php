<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Povly\MoonShineImageEditor\Services\BatchProcessService;

final class BatchProcessController extends Controller
{
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
        $disk = config('moonshine.media_manager.disk', 'public');

        if (empty($files)) {
            return response()->json([
                'status' => false,
                'message' => __('image-editor::image-editor.no_files_selected'),
            ], 422);
        }

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
