<?php

declare(strict_types=1);

namespace Povly\MoonShineImageConstructor;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Povly\MoonShineImageConstructor\Listeners\DeleteImageConversions;
use Povly\MoonShineImageConstructor\Listeners\OptimizeUploadedImage;
use YuriZoom\MoonShineMediaManager\Contracts\MediaManagerRegistryInterface;
use YuriZoom\MoonShineMediaManager\Events\MediaManagerFileDeleted;
use YuriZoom\MoonShineMediaManager\Events\MediaManagerFileUploaded;

class ImageConstructorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'image-constructor');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'image-constructor');
        $this->loadRoutesFrom(__DIR__.'/../routes/routes.php');
        $this->mergeConfigFrom(__DIR__.'/../config/image-constructor.php', 'moonshine.image_constructor');

        $this->publishes([
            __DIR__.'/../dist/filerobot-image-editor.min.js' => public_path('vendor/image-constructor/filerobot-image-editor.min.js'),
            __DIR__.'/../dist/image-constructor.js' => public_path('vendor/image-constructor/image-constructor.js'),
            __DIR__.'/../resources/css/image-constructor.css' => public_path('vendor/image-constructor/image-constructor.css'),
        ], 'image-constructor-assets');

        $this->publishes([
            __DIR__.'/../config/image-constructor.php' => config_path('moonshine/image_constructor.php'),
        ], 'image-constructor-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/image-constructor'),
        ], 'image-constructor-lang');

        $this->app->resolving(MediaManagerRegistryInterface::class, function (MediaManagerRegistryInterface $registry): void {
            $registry->addFileAction('image-constructor', [
                'icon' => 'sparkles',
                'class' => 'btn-sm btn-accent',
                'label' => __('image-constructor::image-constructor.edit_image'),
                'x-show' => '!file.isDir && file.type === "image"',
                'click' => '$store.ic.open(file)',
            ]);
        });

        Event::listen(MediaManagerFileUploaded::class, OptimizeUploadedImage::class);
        Event::listen(MediaManagerFileDeleted::class, DeleteImageConversions::class);
    }

    public static function renderModal(): string
    {
        $locale = config('moonshine.image_constructor.locale') ?? app()->getLocale();
        $translations = __('image-constructor::image-constructor');

        $toastMessages = [
            'saved' => $translations['toast_saved'] ?? 'Image saved successfully',
            'saveFailed' => $translations['toast_save_failed'] ?? 'Failed to save image',
            'editorNotLoaded' => $translations['toast_editor_not_loaded'] ?? 'Image editor not loaded',
        ];

        $config = [
            'saveUrl' => route('moonshine.image-constructor.save'),
            'availableFormats' => config('moonshine.image_constructor.available_formats', ['png', 'jpg']),
            'quality' => config('moonshine.image_constructor.quality', ['jpg' => 85, 'png' => 92]),
            'tabs' => config('moonshine.image_constructor.default_tabs'),
            'defaultTab' => config('moonshine.image_constructor.default_tab'),
            'defaultTool' => config('moonshine.image_constructor.default_tool'),
            'theme' => config('moonshine.image_constructor.theme'),
            'watermarkGallery' => config('moonshine.image_constructor.watermark_gallery'),
            'locale' => $locale,
            'translations' => $translations,
            'toastMessages' => $toastMessages,
        ];

        return view('image-constructor::constructor', ['config' => $config])->render();
    }
}
