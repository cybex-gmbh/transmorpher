<?php

namespace App\Enums;

use App\Interfaces\MediaHandlerInterface;
use Exception;

enum MediaType: string
{
    case IMAGE = 'image';
    case VIDEO = 'video';
    case DOCUMENT = 'document';

    /**
     * @return MediaHandlerInterface
     */
    public function handler(): MediaHandlerInterface
    {
        return app(config(sprintf('transmorpher.media_handlers.%s', $this->value)));
    }

    /**
     * Get the prefix used in file paths and URLs.
     *
     * @return string
     */
    public function prefix(): string
    {
        return match ($this) {
            self::IMAGE => 'images',
            self::VIDEO => 'videos',
            self::DOCUMENT => 'documents'
        };
    }

    /**
     * Get whether this media is instantly available at its public path.
     *
     * @return bool
     */
    public function isInstantlyAvailable(): bool
    {
        return match ($this) {
            self::IMAGE,
            self::DOCUMENT => true,
            self::VIDEO => false
        };
    }

    /**
     * Get whether this media needs multiple paths invalidated:
     * - <identifier>,
     * - <identifier>/,
     * - <identifier>/*
     *
     * If not, only '<identifier>/*' is invalidated.
     *
     * Images and documents may be requested without transformations.
     * These paths also need to be invalidated.
     *
     * @return bool
     */
    public function needsNonTransformationPathsInvalidated(): bool
    {
        return match ($this) {
            self::IMAGE,
            self::DOCUMENT => true,
            self::VIDEO => false
        };
    }

    /**
     * Get whether this media type uses its original file extension for derivatives if no explicit format is specified.
     *
     * @throws Exception
     */
    public function usesOriginalFileExtension(): bool
    {
        return match ($this) {
            self::IMAGE => true,
            self::DOCUMENT => false,
            default => throw new Exception('Not available for this media type'),
        };
    }

    /**
     * Get the default extension for this media type, if applicable.
     *
     * @throws Exception
     */
    public function getDefaultExtension(string $originalFileExtension, ?array $transformations = null): string
    {
        return match ($this) {
            self::DOCUMENT => $transformations ? config('transmorpher.document_default_image_format') : $originalFileExtension,
            default => throw new Exception('Not available for this media type'),
        };
    }
}

