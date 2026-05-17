# MoonShine Image Constructor

Image editor for [MoonShine](https://moonshine-laravel.com/) admin panel powered by [Filerobot Image Editor](https://github.com/scaleflex/filerobot-image-editor). Integrates with [MoonShine Media Manager](https://github.com/povly/moonshine-media-manager) to provide in-browser image editing.

## Features

- Full-featured image editor: adjust, annotate, crop, resize, rotate, filters, finetune, watermark
- Saves edited images alongside originals (with `-edited` suffix)
- Localization support (EN, RU) via Laravel lang files
- No CDN dependencies — all assets are local
- Integrates as a file action button in Media Manager

## Requirements

- PHP 8.2+
- Laravel 11+ / 12+
- MoonShine 4.x
- `povly/moonshine-media-manager` ^4.0

## Installation

```bash
composer require povly/moonshine-image-constructor
```

Publish assets:

```bash
php artisan vendor:publish --tag=image-constructor-assets
```

Optionally publish translations for customization:

```bash
php artisan vendor:publish --tag=image-constructor-lang
```

## Setup

Add the modal renderer to your MoonShine layout:

```php
use YuriZoom\MoonShineImageConstructor\ImageConstructorServiceProvider;

final class MoonShineLayout extends AppLayout
{
    protected function getFooter(): string
    {
        return ImageConstructorServiceProvider::renderModal();
    }
}
```

This renders a hidden modal with the image editor. The "Edit Image" button automatically appears in the Media Manager for image files.

## Configuration

Publish the config file (optional):

```bash
php artisan vendor:publish --tag=moonshine-image_constructor-config
```

```php
// config/moonshine.image_constructor.php
return [
    'default_save_type' => 'png',
    'default_save_quality' => 92,
    'default_tabs' => ['Adjust', 'Annotate', 'Watermark', 'Finetune', 'Filters'],
    'default_tab' => null,
    'default_tool' => null,
    'overwrite_original' => false,
    'locale' => null, // null = auto-detect from app()->getLocale()
    'theme' => [
        'palette' => [
            'bg-primary-active' => '#ECF3FF',
        ],
        'typography' => [
            'fontFamily' => 'Roboto, Arial',
        ],
    ],
    'watermark_gallery' => [],
];
```

### Config Options

| Option | Default | Description |
|--------|---------|-------------|
| `default_save_type` | `png` | Default image format for saved files (`png`, `jpeg`, `webp`) |
| `default_save_quality` | `92` | Default quality (1–100) |
| `default_tabs` | `['Adjust', 'Annotate', ...]` | Visible editor tabs |
| `default_tab` | `null` | Tab opened by default |
| `default_tool` | `null` | Tool selected by default |
| `overwrite_original` | `false` | Overwrite original file or save with `-edited` suffix |
| `locale` | `null` | Editor language (`null` = auto-detect, `'en'`, `'ru'`) |
| `theme` | `[]` | Filerobot theme customization |
| `watermark_gallery` | `[]` | Array of watermark images for the Watermark tab |

## Localization

Translations are loaded from the package automatically. Supported languages:

- `en` — English (default)
- `ru` — Russian

To override translations, publish them:

```bash
php artisan vendor:publish --tag=image-constructor-lang
```

Files will be published to `lang/vendor/image-constructor/en/` and `lang/vendor/image-constructor/ru/`.

## How It Works

1. User clicks the **"Edit Image"** button in Media Manager
2. A modal opens with the Filerobot Image Editor loaded
3. User edits the image (crop, filters, text, etc.)
4. On save, the edited image is uploaded to the same directory as the original
5. If `overwrite_original` is `false` (default), the file is saved with a `-edited` suffix (e.g., `photo-edited.png`)
6. If a file with that name already exists, a counter is appended (e.g., `photo-edited-1.png`)

## License

MIT
