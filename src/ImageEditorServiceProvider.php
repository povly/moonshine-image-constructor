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
use Povly\MoonShineImageEditor\Contracts\ImageOptimizerInterface;
use Povly\MoonShineImageEditor\Contracts\SettingsRepositoryInterface;
use Povly\MoonShineImageEditor\Listeners\DeleteImageConversions;
use Povly\MoonShineImageEditor\Listeners\OptimizeUploadedImage;
use Povly\MoonShineImageEditor\Pages\ImageSettingsPage;
use Povly\MoonShineImageEditor\Services\ImageOptimizer;
use Povly\MoonShineImageEditor\Services\SettingsService;
use Povly\MoonShineImageEditor\Support\ImageEditorRenderer;
use Povly\MoonShineImageEditor\Support\OptimizationDispatcher;
use YuriZoom\MoonShineMediaManager\Contracts\MediaManagerRegistryInterface;
use YuriZoom\MoonShineMediaManager\Events\MediaManagerFileDeleted;
use YuriZoom\MoonShineMediaManager\Events\MediaManagerFileUploaded;

class ImageEditorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/image-editor.php', 'moonshine.image_editor');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'image-editor');

        $this->app->singleton(SettingsRepositoryInterface::class, SettingsService::class);
        $this->app->singleton(SettingsService::class);
        $this->app->singleton(ImageEditorRenderer::class);
        $this->app->singleton(OptimizationDispatcher::class);

        $this->app->bind(ImageOptimizerInterface::class, static function ($app, array $parameters) {
            return new ImageOptimizer(
                $parameters['fullPath'],
                $parameters['config'] ?? [],
            );
        });

        $this->app->booted(function (): void {
            try {
                app(SettingsRepositoryInterface::class)->applyToConfig();
            } catch (\Throwable $e) {
                Log::warning('[ImageEditor] Could not apply settings', [
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    public function boot(CoreContract $core, MenuManagerContract $menu): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'image-editor');
        $this->loadRoutesFrom(__DIR__.'/../routes/routes.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

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

        $this->registerMediaManagerIntegration();

        Event::listen(MediaManagerFileUploaded::class, OptimizeUploadedImage::class);
        Event::listen(MediaManagerFileDeleted::class, DeleteImageConversions::class);

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

    private function registerMediaManagerIntegration(): void
    {
        $this->app->resolving(MediaManagerRegistryInterface::class, function (MediaManagerRegistryInterface $registry): void {
            $registry->addFileAction('image-editor', [
                'icon' => 'sparkles',
                'class' => 'btn-sm btn-accent',
                'label' => __('image-editor::image-editor.edit_image'),
                'x-show' => '!file.isDir && file.type === "image"',
                'click' => '$store.ic.open(file)',
            ]);
        });
    }

    public static function renderModal(): string
    {
        return app(ImageEditorRenderer::class)->renderModal();
    }
}
