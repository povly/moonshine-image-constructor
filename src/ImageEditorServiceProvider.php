<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Povly\MoonShineImageEditor\Listeners\DeleteImageConversions;
use Povly\MoonShineImageEditor\Listeners\OptimizeUploadedImage;
use YuriZoom\MoonShineMediaManager\Contracts\MediaManagerRegistryInterface;
use YuriZoom\MoonShineMediaManager\Events\MediaManagerFileDeleted;
use YuriZoom\MoonShineMediaManager\Events\MediaManagerFileUploaded;

class ImageEditorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'image-editor');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'image-editor');
        $this->loadRoutesFrom(__DIR__.'/../routes/routes.php');
        $this->mergeConfigFrom(__DIR__.'/../config/image-editor.php', 'moonshine.image_editor');

        $this->publishes([
            __DIR__.'/../dist/filerobot-image-editor.min.js' => public_path('vendor/image-editor/filerobot-image-editor.min.js'),
            __DIR__.'/../dist/image-editor.js' => public_path('vendor/image-editor/image-editor.js'),
            __DIR__.'/../dist/image-editor.css' => public_path('vendor/image-editor/image-editor.css'),
        ], 'image-editor-assets');

        $this->publishes([
            __DIR__.'/../config/image-editor.php' => config_path('moonshine/image_editor.php'),
        ], 'image-editor-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/image-editor'),
        ], 'image-editor-lang');

        $this->app->resolving(MediaManagerRegistryInterface::class, function (MediaManagerRegistryInterface $registry): void {
            $registry->addFileAction('image-editor', [
                'icon' => 'sparkles',
                'class' => 'btn-sm btn-accent',
                'label' => __('image-editor::image-editor.edit_image'),
                'x-show' => '!file.isDir && file.type === "image"',
                'click' => '$store.ic.open(file)',
            ]);
        });

        Event::listen(MediaManagerFileUploaded::class, OptimizeUploadedImage::class);
        Event::listen(MediaManagerFileDeleted::class, DeleteImageConversions::class);
    }

    public static function renderModal(): string
    {
        $locale = config('moonshine.image_editor.locale') ?? app()->getLocale();
        $translations = __('image-editor::image-editor');

        $toastMessages = [
            'saved' => $translations['toast_saved'] ?? 'Image saved successfully',
            'saveFailed' => $translations['toast_save_failed'] ?? 'Failed to save image',
            'editorNotLoaded' => $translations['toast_editor_not_loaded'] ?? 'Image editor not loaded',
        ];

        $config = [
            'saveUrl' => route('moonshine.image-editor.save'),
            'availableFormats' => config('moonshine.image_editor.available_formats', ['png', 'jpg']),
            'quality' => config('moonshine.image_editor.quality', ['jpg' => 85, 'png' => 92]),
            'tabs' => config('moonshine.image_editor.default_tabs'),
            'defaultTab' => config('moonshine.image_editor.default_tab'),
            'defaultTool' => config('moonshine.image_editor.default_tool'),
            'theme' => config('moonshine.image_editor.theme'),
            'watermarkGallery' => config('moonshine.image_editor.watermark_gallery'),
            'locale' => $locale,
            'translations' => $translations,
            'toastMessages' => $toastMessages,
        ];

        return view('image-editor::editor', ['config' => $config])->render();
    }
}
