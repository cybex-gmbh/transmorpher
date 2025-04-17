<?php

namespace App\Classes\MediaHandler;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Enums\ResponseState;
use App\Interfaces\MediaHandlerInterface;
use CdnHelper;
use Illuminate\Contracts\Filesystem\Filesystem;
use Log;
use Throwable;

abstract class MediaHandler implements MediaHandlerInterface
{
    protected MediaType $type;
    protected MediaStorage $derivativesStorage;
    protected ResponseState $uploadSuccessful;
    protected ResponseState $uploadFailed;
    protected ResponseState $versionSetSuccessful;
    protected ResponseState $versionSetFailed;

    /**
     * @return Filesystem
     */
    public function getDerivativesDisk(): Filesystem
    {
        return $this->derivativesStorage->getDisk();
    }

    /**
     * @param string $basePath
     * @return bool
     */
    public function invalidateCdnCache(string $basePath): bool
    {
        if (CdnHelper::isConfigured()) {
            try {
                CdnHelper::invalidateMedia($this->type, $basePath);
            } catch (Throwable $throwable) {
                Log::error(sprintf('CDN invalidation failed for basepath %s: %s', $basePath, $throwable->getMessage()));
                return false;
            }
        }

        return true;
    }
}
