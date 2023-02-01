<?php

namespace App\Enums;

use App\Interfaces\ConverterInterface;

enum Converter: string
{
    case JPG = 'jpg';
    case PNG = 'png';
    case GIF = 'gif';
    case WEBP = 'webp';

    public function getConverter(): ConverterInterface
    {
        return app(config(sprintf('transmorpher.converters.%s', $this->value)));
    }
}
