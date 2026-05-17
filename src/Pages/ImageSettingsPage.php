<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor\Pages;

use MoonShine\Laravel\Pages\Page;
use MoonShine\Support\Enums\FormMethod;
use MoonShine\UI\Components\FlexibleRender;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Heading;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Tabs;
use MoonShine\UI\Components\Tabs\Tab;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use Povly\MoonShineImageEditor\Services\SettingsService;

final class ImageSettingsPage extends Page
{
    protected string $title = 'Image Editor Settings';

    protected function components(): iterable
    {
        $settingsService = app(SettingsService::class);
        $settings = $settingsService->getSettings();

        return [
            Tabs::make([
                Tab::make(__('image-editor::image-editor.tab_settings'), [
                    $this->buildSettingsForm($settings),
                ])->icon('cog'),

                Tab::make(__('image-editor::image-editor.tab_batch'), [
                    FlexibleRender::make(
                        view('image-editor::batch-process', [
                            'scanUrl' => route('moonshine.image-editor.batch.scan'),
                            'startUrl' => route('moonshine.image-editor.batch.start'),
                            'progressUrl' => route('moonshine.image-editor.batch.progress'),
                            'clearLogUrl' => route('moonshine.image-editor.batch.clear-log'),
                        ])->render(),
                    ),
                ])->icon('server'),
            ]),
        ];
    }

    private function buildSettingsForm(array $settings): FormBuilder
    {
        $flat = $this->flattenSettings($settings);

        return FormBuilder::make(route('moonshine.image-editor.settings.save'))
            ->async()
            ->method(FormMethod::POST)
            ->fields([
                Box::make(__('image-editor::image-editor.section_quality'), [
                    Number::make(__('image-editor::image-editor.quality_jpg'), 'quality__jpg')
                        ->min(1)->max(100)
                        ->default(85),

                    Number::make(__('image-editor::image-editor.quality_webp'), 'convert__webp__quality')
                        ->min(1)->max(100)
                        ->default(80),

                    Number::make(__('image-editor::image-editor.quality_avif'), 'convert__avif__quality')
                        ->min(1)->max(100)
                        ->default(65),
                ]),

                Box::make(__('image-editor::image-editor.section_conversion'), [
                    Switcher::make(__('image-editor::image-editor.generate_webp'), 'convert__webp__enabled')
                        ->default(false),

                    Switcher::make(__('image-editor::image-editor.generate_avif'), 'convert__avif__enabled')
                        ->default(false),
                ]),

                Box::make(__('image-editor::image-editor.section_optimization'), [
                    Switcher::make(__('image-editor::image-editor.strip_metadata'), 'optimize__strip_metadata')
                        ->default(true),

                    Number::make(__('image-editor::image-editor.max_width'), 'optimize__max_width')
                        ->min(0),

                    Number::make(__('image-editor::image-editor.max_height'), 'optimize__max_height')
                        ->min(0),
                ]),

                Box::make(__('image-editor::image-editor.section_queue'), [
                    Switcher::make(__('image-editor::image-editor.enable_queue'), 'queue__enabled')
                        ->default(false),

                    Heading::make(''),

                    Number::make(__('image-editor::image-editor.queue_delay'), 'queue__delay')
                        ->min(0)
                        ->default(60),
                ]),
            ])
            ->fill($flat)
            ->submit(__('image-editor::image-editor.save_settings'), [
                'class' => 'btn-primary btn-lg',
            ]);
    }

    private function flattenSettings(array $settings, string $prefix = ''): array
    {
        $flat = [];

        foreach ($settings as $key => $value) {
            $flatKey = $prefix === '' ? $key : "{$prefix}__{$key}";

            if (is_array($value) && ! empty($value) && ! array_is_list($value)) {
                $flat = [...$flat, ...$this->flattenSettings($value, $flatKey)];
            } else {
                $flat[$flatKey] = $value;
            }
        }

        return $flat;
    }
}
