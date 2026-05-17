<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Available save formats
    |--------------------------------------------------------------------------
    |
    | Formats shown in the save dialog selector.
    | PNG and JPG only — WebP/AVIF are automatic conversions.
    |
    */
    'available_formats' => ['png', 'jpg'],

    /*
    |--------------------------------------------------------------------------
    | Save quality (1–100)
    |--------------------------------------------------------------------------
    */
    'quality' => [
        'jpg' => 82,
    ],

    /*
    |--------------------------------------------------------------------------
    | Optimization & conversion
    |--------------------------------------------------------------------------
    |
    | After saving, optimize the image and generate WebP/AVIF versions.
    | Uses intervention/image library.
    |
    | Size comparison: if optimized/conversion is larger than the source,
    | the smaller version is kept and the larger one is deleted.
    |
    | delete_original_on_convert: when converting PNG→JPG, delete the
    | intermediate PNG file after successful conversion.
    |
    */
    'optimize' => [
        'enabled' => true,
        'strip_metadata' => true,
        'max_width' => null,
        'max_height' => null,
    ],

    'convert' => [
        'webp' => [
            'enabled' => false,
            'quality' => 80,
        ],
        'avif' => [
            'enabled' => false,
            'quality' => 65,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Offload optimization & conversion to a queue job.
    | The user gets an instant response; processing happens later.
    |
    */
    'queue' => [
        'enabled' => false,
        'connection' => null,
        'queue' => 'images',
        'delay' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Editor settings
    |--------------------------------------------------------------------------
    */
    'default_tabs' => ['Adjust', 'Annotate', 'Watermark', 'Finetune', 'Filters'],

    'default_tab' => null,

    'default_tool' => null,

    'overwrite_original' => false,

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
