<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\MenuManager\MenuManagerContract;
use MoonShine\MenuManager\MenuItem;
use Povly\MoonShineImageEditor\Console\Commands\ImageClearConversionsCommand;
use Povly\MoonShineImageEditor\Console\Commands\ImageOptimizeCommand;
use Povly\MoonShineImageEditor\Console\Commands\ImageResetSettingsCommand;
use Povly\MoonShineImageEditor\Listeners\DeleteImageConversions;
use Povly\MoonShineImageEditor\Listeners\OptimizeUploadedImage;
use Povly\MoonShineImageEditor\Pages\ImageSettingsPage;
use Povly\MoonShineImageEditor\Services\SettingsService;
use YuriZoom\MoonShineMediaManager\Contracts\MediaManagerRegistryInterface;
use YuriZoom\MoonShineMediaManager\Events\MediaManagerFileDeleted;
use YuriZoom\MoonShineMediaManager\Events\MediaManagerFileUploaded;

class ImageEditorServiceProvider extends ServiceProvider
{
    public function boot(CoreContract $core, MenuManagerContract $menu): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'image-editor');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'image-editor');
        $this->loadRoutesFrom(__DIR__.'/../routes/routes.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
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

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'image-editor-migrations');

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

        $this->app->singleton(SettingsService::class);

        $core->pages([
            ImageSettingsPage::class,
        ]);

        $menu->add([
            MenuItem::make(ImageSettingsPage::class, __('image-editor::image-editor.menu_title'))->icon('cog'),
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ImageOptimizeCommand::class,
                ImageClearConversionsCommand::class,
                ImageResetSettingsCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->booted(function (): void {
            try {
                app(SettingsService::class)->applyToConfig();
            } catch (\Throwable $e) {
                // Settings table may not exist yet during migration
                Log::warning('[ImageEditor] Could not apply settings', [
                    'error' => $e->getMessage(),
                ]);
            }
        });
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
