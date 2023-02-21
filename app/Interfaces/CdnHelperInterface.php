<?php

namespace App\Interfaces;

interface CdnHelperInterface
{
    /**
     * Create a CDN invalidation for an image.
     *
     * @param string $invalidationPath
     *
     * @return void
     */
    public function invalidateImage(string $invalidationPath): void;

    /**
     * Create a CDN invalidation for a video.
     *
     * @param string $invalidationPath
     *
     * @return void
     */
    public function invalidateVideo(string $invalidationPath): void;

    /**
     * Return whether the CDN is configured.
     *
     * @return bool
     */
    public function isConfigured(): bool;
}
