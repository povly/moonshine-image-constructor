<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor\Enums;

enum ImageExtension: string
{
    case JPG = 'jpg';
    case JPEG = 'jpeg';
    case PNG = 'png';
    case GIF = 'gif';
    case WEBP = 'webp';
    case AVIF = 'avif';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<string>
     */
    public static function optimizable(): array
    {
        return ['jpg', 'jpeg', 'png'];
    }

    /**
     * @return list<string>
     */
    public static function sources(): array
    {
        return ['jpg', 'jpeg', 'png', 'gif'];
    }

    /**
     * @return list<string>
     */
    public static function conversions(): array
    {
        return ['webp', 'avif'];
    }
}
