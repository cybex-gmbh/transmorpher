<?php

namespace App\Interfaces;

interface CdnHelperInterface
{
    /**
     * Create a CDN invalidation.
     *
     * @param array $invalidationPaths
     *
     * @return void
     */
    public function invalidate(array $invalidationPaths): void;

    /**
     * Return whether the CDN is configured.
     *
     * @return bool
     */
    public function isConfigured(): bool;
}
