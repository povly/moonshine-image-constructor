<?php

declare(strict_types=1);

namespace YuriZoom\MoonShineImageConstructor;

use Illuminate\Support\ServiceProvider;
use YuriZoom\MoonShineMediaManager\Contracts\MediaManagerRegistryInterface;

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
        ], 'image-constructor-assets');

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
            'defaultSaveType' => config('moonshine.image_constructor.default_save_type', 'png'),
            'defaultSaveQuality' => config('moonshine.image_constructor.default_save_quality', 92),
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
