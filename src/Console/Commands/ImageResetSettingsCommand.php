<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor\Console\Commands;

use Illuminate\Console\Command;
use Povly\MoonShineImageEditor\Models\ImageEditorSetting;

final class ImageResetSettingsCommand extends Command
{
    protected $signature = 'image-editor:reset-settings';

    protected $description = 'Reset image editor settings to defaults';

    public function handle(): int
    {
        ImageEditorSetting::query()->delete();
        $this->info('Settings reset to defaults.');

        return self::SUCCESS;
    }
}
