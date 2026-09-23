<?php

namespace App\Interfaces;

use App\Enums\MediaType;

interface CdnHelperInterface
{
    /**
     * Create a CDN invalidation for media.
     *
     * @param MediaType $type
     * @param string $invalidationPath
     *
     * @return void
     */
    public function invalidateMedia(MediaType $type, string $invalidationPath): void;

    /**
     * Return whether the CDN is configured.
     *
     * @return bool
     */
    public function isConfigured(): bool;
}
