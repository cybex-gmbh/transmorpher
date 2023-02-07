<?php

namespace App\Enums;

use App\Interfaces\ConvertInterface;

enum ImageFormat: string
{
    case JPG = 'jpg';
    case PNG = 'png';
    case GIF = 'gif';
    case WEBP = 'webp';

    /**
     * Retrieve converter class from the value specified in the transmorpher config.
     *
     * @return ConvertInterface
     */
    public function getConverter(): ConvertInterface
    {
        return app(config(sprintf('transmorpher.convert_classes.%s', $this->value)));
    }

    /**
     * Retrieve the mime types which are defined in the enum cases.
     *
     * @return array
     */
    public static function getMimeTypes(): array
    {
        return array_column(self::cases(), 'value');
    }
}
