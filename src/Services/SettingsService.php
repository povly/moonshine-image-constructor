<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor\Services;

use Povly\MoonShineImageEditor\Jobs\ProcessEditedImage;
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
        $settings = $this->validateSettings($settings);

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
        $settings = $this->validateSettings($settings);

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

    /**
     * Validate and sanitize settings before saving or applying to config.
     * Removes unknown keys and enforces value constraints.
     */
    private function validateSettings(array $settings): array
    {
        $validated = [];

        if (isset($settings['quality']) && is_array($settings['quality'])) {
            $validated['quality'] = [];
            foreach ($settings['quality'] as $format => $value) {
                if (is_string($format) && in_array($format, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'], true)) {
                    $validated['quality'][$format] = is_int($value) ? max(1, min(100, $value)) : null;
                }
            }
        }

        if (isset($settings['optimize']) && is_array($settings['optimize'])) {
            $opt = $settings['optimize'];
            $validated['optimize'] = [
                'enabled' => isset($opt['enabled']) ? (bool) $opt['enabled'] : true,
                'strip_metadata' => isset($opt['strip_metadata']) ? (bool) $opt['strip_metadata'] : true,
                'max_width' => isset($opt['max_width']) && $opt['max_width'] !== null ? min(10000, max(0, (int) $opt['max_width'])) : null,
                'max_height' => isset($opt['max_height']) && $opt['max_height'] !== null ? min(10000, max(0, (int) $opt['max_height'])) : null,
            ];
        }

        if (isset($settings['convert']) && is_array($settings['convert'])) {
            $validated['convert'] = [];
            foreach (['webp', 'avif'] as $format) {
                if (isset($settings['convert'][$format]) && is_array($settings['convert'][$format])) {
                    $f = $settings['convert'][$format];
                    $validated['convert'][$format] = [
                        'enabled' => isset($f['enabled']) ? (bool) $f['enabled'] : false,
                        'quality' => isset($f['quality']) ? max(1, min(100, (int) $f['quality'])) : ($format === 'webp' ? 80 : 65),
                    ];
                }
            }
        }

        if (isset($settings['queue']) && is_array($settings['queue'])) {
            $q = $settings['queue'];
            $validated['queue'] = [
                'enabled' => isset($q['enabled']) ? (bool) $q['enabled'] : false,
                'connection' => isset($q['connection']) && is_string($q['connection']) ? $q['connection'] : null,
                'queue' => isset($q['queue']) && is_string($q['queue']) ? $q['queue'] : 'images',
                'delay' => isset($q['delay']) && $q['delay'] !== null ? max(0, (int) $q['delay']) : null,
            ];
        }

        return $validated;
    }

    /**
     * Shared method for dispatching image optimization.
     * Handles both sync and queue modes.
     */
    public function dispatchOptimization(string $fullPath, string $relativePath, string $disk, ?array $optimizerConfig = null): void
    {
        $optimizerConfig ??= $this->getOptimizerConfig();

        if (config('moonshine.image_editor.queue.enabled', false)) {
            $connection = config('moonshine.image_editor.queue.connection');
            $queue = config('moonshine.image_editor.queue.queue', 'images');
            $delay = config('moonshine.image_editor.queue.delay');

            $job = new ProcessEditedImage($fullPath, $relativePath, $disk, $optimizerConfig);

            if ($connection) {
                $job->onConnection($connection);
            }

            $job->onQueue($queue);

            if ($delay !== null) {
                $job->delay(now()->addSeconds((int) $delay));
            }

            dispatch($job);
        } else {
            $optimizer = new ImageOptimizer($fullPath, $optimizerConfig);
            $optimizer->process();
        }
    }
}
