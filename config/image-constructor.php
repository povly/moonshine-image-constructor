<?php

declare(strict_types=1);

return [
    'default_save_type' => 'png',

    'default_save_quality' => 92,

    'default_tabs' => ['Adjust', 'Annotate', 'Watermark', 'Finetune', 'Filters'],

    'default_tab' => null,

    'default_tool' => null,

    'overwrite_original' => false,

    /*
    |--------------------------------------------------------------------------
    | Language
    |--------------------------------------------------------------------------
    |
    | The locale used for the Filerobot Image Editor interface.
    | Supported: 'en', 'ru'. Null = auto-detect from app()->getLocale().
    |
    */
    'locale' => null,

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
