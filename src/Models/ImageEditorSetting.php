<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor\Models;

use Illuminate\Database\Eloquent\Model;

final class ImageEditorSetting extends Model
{
    protected $table = 'image_editor_settings';

    protected $fillable = [
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }
}
