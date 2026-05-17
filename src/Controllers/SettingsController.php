<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use MoonShine\Laravel\Http\Responses\MoonShineJsonResponse;
use MoonShine\UI\Components\Toast;
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

    public function save(Request $request): MoonShineJsonResponse
    {
        $flat = $request->except(['_token', '_method']);
        $settings = $this->unflattenSettings($flat);

        $this->settingsService->saveSettings($settings);

        return MoonShineJsonResponse::make(
            Toast::make(__('image-editor::image-editor.settings_saved'), type: 'success')
        );
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
                    } elseif (is_numeric($value) && str_contains($key, 'quality') || str_contains($key, 'max_') || str_contains($key, 'delay')) {
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
