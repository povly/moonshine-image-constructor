<?php

use Illuminate\Support\Facades\Route;
use Povly\MoonShineImageEditor\Controllers\ImageEditorController;

$middleware = config('moonshine.auth.middleware');
$middleware = is_array($middleware) ? $middleware : [$middleware];

Route::group([
    'prefix' => config('moonshine.route.prefix'),
    'as' => 'moonshine.',
    'middleware' => [...$middleware, 'web'],
], function () {
    Route::post('image-editor/save', [ImageEditorController::class, 'save'])
        ->name('image-editor.save');
});
