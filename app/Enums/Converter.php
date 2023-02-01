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
        return app(config(sprintf('transmorpher.converters.%s', $this->value)));
    }
}
