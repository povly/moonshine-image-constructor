<?php

use Illuminate\Support\Facades\Route;
use Povly\MoonShineImageConstructor\Controllers\ImageConstructorController;

$middleware = config('moonshine.auth.middleware');
$middleware = is_array($middleware) ? $middleware : [$middleware];

Route::group([
    'prefix' => config('moonshine.route.prefix'),
    'as' => 'moonshine.',
    'middleware' => [...$middleware, 'web'],
], function () {
    Route::post('image-constructor/save', [ImageConstructorController::class, 'save'])
        ->name('image-constructor.save');
});
