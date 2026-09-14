<?php

namespace App\Classes\Video\Encoder;

use App\Interfaces\VideoEncoderInterface;
use TypeError;

abstract class AbstractEncoder implements VideoEncoderInterface
{
    public function outputParameters(bool $forMp4Fallback = false): array
    {
        $parametersPath = $forMp4Fallback ? 'mp4_parameters' : 'streaming_parameters';
        $formatParameters = $this->getArrayFromConfig($parametersPath);
        $encoderParameters = $this->getArrayFromConfig('parameters');

        return array_merge($formatParameters, $encoderParameters);
    }

    public function streamingCodec(): string
    {
        return $this->getStringFromConfig('streaming_codec');
    }

    protected function getArrayFromConfig(string $key): array
    {
        $value = config(sprintf('transmorpher.interchangeable.video.encoder.%s.%s', $this->name(), $key));

        if (!is_array($value)) {
            throw new TypeError(sprintf('Invalid encoder configuration "%s" for "%s". Expected array.', $key, $this->name()));
        }

        return $value;
    }

    protected function getStringFromConfig(string $key): string
    {
        $value = config(sprintf('transmorpher.interchangeable.video.encoder.%s.%s', $this->name(), $key));

        if (!is_string($value)) {
            throw new TypeError(sprintf('Invalid encoder configuration "%s" for "%s". Expected string.', $key, $this->name()));
        }

        return $value;
    }
}
