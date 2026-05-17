<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor\Services;

use Povly\MoonShineImageEditor\Models\ImageEditorSetting;

final class SettingsService
{
    private ?array $cachedSettings = null;

    public function getSettings(): array
    {
        if ($this->cachedSettings !== null) {
            return $this->cachedSettings;
        }

        $defaults = $this->getConfigDefaults();
        $dbSettings = $this->getDbSettings();

        $this->cachedSettings = array_replace_recursive($defaults, $dbSettings);

        return $this->cachedSettings;
    }

    public function saveSettings(array $settings): void
    {
        $this->flattenBooleanStrings($settings);

        $existing = $this->getDbSettings();
        $merged = array_replace_recursive($existing, $settings);

        $row = ImageEditorSetting::firstOrNew();
        $row->settings = $merged;
        $row->save();

        $this->cachedSettings = null;

        $this->applyToConfig();
    }

    public function getOptimizerConfig(): array
    {
        $settings = $this->getSettings();

        return [
            'quality' => $settings['quality'] ?? [],
            'optimize' => $settings['optimize'] ?? [],
            'convert' => $settings['convert'] ?? [],
        ];
    }

    /** Mutates Laravel config at runtime so that queued jobs use fresh settings. */
    public function applyToConfig(?array $settings = null): void
    {
        $settings ??= $this->getSettings();

        foreach (['quality', 'optimize', 'convert', 'queue'] as $key) {
            if (isset($settings[$key]) && ! empty($settings[$key])) {
                config()->set("moonshine.image_editor.$key", $settings[$key]);
            }
        }
    }

    private function getConfigDefaults(): array
    {
        return [
            'quality' => config('moonshine.image_editor.quality', ['jpg' => 82]),
            'optimize' => config('moonshine.image_editor.optimize', [
                'enabled' => true,
                'strip_metadata' => true,
                'max_width' => null,
                'max_height' => null,
            ]),
            'convert' => config('moonshine.image_editor.convert', [
                'webp' => ['enabled' => false, 'quality' => 80],
                'avif' => ['enabled' => false, 'quality' => 65],
            ]),
            'queue' => config('moonshine.image_editor.queue', [
                'enabled' => false,
                'connection' => null,
                'queue' => 'images',
                'delay' => 60,
            ]),
        ];
    }

    private function getDbSettings(): array
    {
        $row = ImageEditorSetting::first();

        if ($row === null || $row->settings === null) {
            return [];
        }

        return $row->settings;
    }

    /** Form submissions send booleans as strings — normalise them. */
    private function flattenBooleanStrings(array &$settings): void
    {
        foreach ($settings as $key => &$value) {
            if (is_array($value)) {
                $this->flattenBooleanStrings($value);
            } elseif ($value === 'true' || $value === '1') {
                $value = true;
            } elseif ($value === 'false' || $value === '0') {
                $value = false;
            } elseif ($value === '' || $value === null) {
                $value = null;
            }
        }
    }
}
