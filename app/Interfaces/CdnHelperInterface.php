<?php

namespace App\Interfaces;

interface CdnHelperInterface
{
    /**
     * Create a CDN invalidation.
     *
     * @param string $invalidationPath
     *
     * @return void
     */
    public function createInvalidation(string $invalidationPath): void;

    /**
     * Return whether the CDN is configured.
     *
     * @return bool
     */
    public function isConfigured(): bool;
}
