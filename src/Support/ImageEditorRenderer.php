<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor\Support;

use Povly\MoonShineImageEditor\Contracts\SettingsRepositoryInterface;

final class ImageEditorRenderer
{
    public function __construct(
        private readonly SettingsRepositoryInterface $settings,
    ) {}

    public function renderModal(): string
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
