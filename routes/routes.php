<?php

use Illuminate\Support\Facades\Route;
use Povly\MoonShineImageEditor\Controllers\BatchProcessController;
use Povly\MoonShineImageEditor\Controllers\ImageEditorController;
use Povly\MoonShineImageEditor\Controllers\SettingsController;

$middleware = config('moonshine.auth.middleware');
$middleware = is_array($middleware) ? $middleware : [$middleware];

Route::group([
    'prefix' => config('moonshine.route.prefix'),
    'as' => 'moonshine.',
    'middleware' => [...$middleware, 'web'],
], function () {
    Route::post('image-editor/save', [ImageEditorController::class, 'save'])
        ->name('image-editor.save');

    Route::get('image-editor/settings', [SettingsController::class, 'load'])
        ->name('image-editor.settings.load');

    Route::post('image-editor/settings', [SettingsController::class, 'save'])
        ->name('image-editor.settings.save');

    Route::get('image-editor/batch/scan', [BatchProcessController::class, 'scan'])
        ->name('image-editor.batch.scan');

    Route::post('image-editor/batch/start', [BatchProcessController::class, 'start'])
        ->name('image-editor.batch.start');

    Route::get('image-editor/batch/progress', [BatchProcessController::class, 'progress'])
        ->name('image-editor.batch.progress');

    Route::post('image-editor/batch/clear-log', [BatchProcessController::class, 'clearLog'])
        ->name('image-editor.batch.clear-log');
});
