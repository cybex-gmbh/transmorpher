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
     * Retrieve the formats which are defined in the enum cases.
     *
     * @return array
     */
    public static function getFormats(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function getMimetypes(): array
    {
        return array_map(fn($format) => $format->getMimetype(), self::cases());
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
     * Get an enum case from a mime type or null.
     *
     * @param $mimeType
     * @return ImageFormat|null
     */
    public static function tryFromMimeType($mimeType): self|null
    {
        return match ($mimeType) {
            'image/jpeg' => self::JPG,
            'image/png' => self::PNG,
            'image/gif' => self::GIF,
            'image/webp' => self::WEBP,
            default => null
        };
    }

    public function getMimetype(): string
    {
        return match ($this) {
            ImageFormat::JPG => 'image/jpeg',
            ImageFormat::PNG => 'image/png',
            ImageFormat::GIF => 'image/gif',
            ImageFormat::WEBP => 'image/webp'
        };
    }

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
