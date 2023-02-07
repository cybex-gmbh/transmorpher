<?php

namespace App\Enums;

use App\Interfaces\ConverterInterface;

enum Converter: string
{
    case JPG = 'jpg';
    case PNG = 'png';
    case GIF = 'gif';
    case WEBP = 'webp';

    /**
     * Retrieve converter class from the value specified in the transmorpher config.
     *
     * @return ConverterInterface
     */
    public function getConverter(): ConverterInterface
    {
        return app(config(sprintf('transmorpher.converter_classes.%s', $this->value)));
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