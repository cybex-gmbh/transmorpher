<?php

namespace App\Classes\Video\Decoder;

use App\Interfaces\VideoDecoderInterface;
use TypeError;

abstract class AbstractDecoder implements VideoDecoderInterface
{
    public function inputParameters(): array
    {
        $parameters = config(sprintf(
            'transmorpher.interchangeable.video.decoder.%s.parameters',
            $this->name()
        ));

        if (!is_array($parameters)) {
            throw new TypeError(sprintf('Invalid decoder parameter configuration for "%s". Expected array.', $this->name()));
        }

        return $parameters;
    }
}
