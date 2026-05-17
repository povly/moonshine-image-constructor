<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use MoonShine\Laravel\Http\Responses\MoonShineJsonResponse;
use MoonShine\Support\Enums\ToastType;
use Povly\MoonShineImageEditor\Services\SettingsService;

final class SettingsController extends Controller
{
    public function __construct(
        private SettingsService $settingsService,
    ) {}

    public function load(): JsonResponse
    {
        return response()->json([
            'settings' => $this->settingsService->getSettings(),
        ]);
    }

    private const ALLOWED_SETTINGS_KEYS = [
        'quality__jpg',
        'convert__webp__quality',
        'convert__webp__enabled',
        'convert__avif__quality',
        'convert__avif__enabled',
        'optimize__strip_metadata',
        'optimize__max_width',
        'optimize__max_height',
        'queue__enabled',
        'queue__delay',
    ];

    public function save(Request $request): MoonShineJsonResponse
    {
        $raw = $request->except(['_token', '_method']);
        $flat = array_intersect_key($raw, array_flip(self::ALLOWED_SETTINGS_KEYS));
        $settings = $this->unflattenSettings($flat);

        $this->settingsService->saveSettings($settings);

        return MoonShineJsonResponse::make()
            ->toast(__('image-editor::image-editor.settings_saved'), ToastType::SUCCESS);
    }

    private function unflattenSettings(array $flat): array
    {
        $result = [];

        foreach ($flat as $key => $value) {
            $parts = explode('__', $key);
            $ref = &$result;

            foreach ($parts as $i => $part) {
                if ($i === count($parts) - 1) {
                    if ($value === 'on' || $value === '1') {
                        $value = true;
                    } elseif ($value === '' || $value === null) {
                        $value = null;
                    } elseif (is_numeric($value) && (str_contains($key, 'quality') || str_contains($key, 'max_') || str_contains($key, 'delay'))) {
                        $value = (int) $value;
                    }

                    $ref[$part] = $value;
                } else {
                    $ref[$part] ??= [];
                    $ref = &$ref[$part];
                }
            }
        }

        return $result;
    }
}
