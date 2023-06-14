<?php

namespace App\Enums;

use App\Classes\Optimizer\FormatOptimizer;
use App\Classes\Optimizer\PngOptimizer;
use App\Interfaces\ConvertInterface;
use App\Interfaces\FormatOptimizerInterface;

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
    public static function getFormats(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get an enum case from a mime type.
     *
     * @param $mimeType
     * @return self
     */
    public static function fromMimeType($mimeType): self
    {
        return match ($mimeType) {
            'image/jpeg' => self::JPG,
            'image/png' => self::PNG,
            'image/gif' => self::GIF,
            'image/webp' => self::WEBP
        };
    }

    /**
     * Get the optimizer for a case.
     *
     * @return FormatOptimizerInterface
     */
    public function getOptimizer(): FormatOptimizerInterface
    {
        return match ($this) {
            ImageFormat::PNG => app(PngOptimizer::class),
            default => app(FormatOptimizer::class)
        };
    }
}
