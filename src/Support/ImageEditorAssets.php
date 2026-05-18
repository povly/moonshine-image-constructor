<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor\Support;

use MoonShine\AssetManager\Css;
use MoonShine\AssetManager\Js;
use MoonShine\Contracts\AssetManager\AssetElementContract;

final class ImageEditorAssets
{
    /**
     * @return list<AssetElementContract>
     */
    public static function get(): array
    {
        return [
            Js::make('/vendor/image-editor/filerobot-image-editor.min.js')->defer(),
            Js::make('/vendor/image-editor/image-editor.js')->defer(),
            Css::make('/vendor/image-editor/image-editor.css'),
        ];
    }
}
