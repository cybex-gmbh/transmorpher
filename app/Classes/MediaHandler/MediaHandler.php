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

    public function getAllowedMimetypesAsString(): string
    {
        return $this->getAllowedMimetypes()->join(', ');
    }

    /**
     * Validates the passed mimetype against allowed mimetypes.
     * This has to be done after all chunks have been received, because the mime type of the received chunks is 'application/octet-stream'.
     *
     * For videos:
     *  Mimetypes to mimes:
     *      video/x-msvideo => avi
     *      video/mpeg => mpeg mpg mpe m1v m2v
     *      video/ogg => ogv
     *      video/webm => webm
     *      video/mp4 => mp4 mp4v mpg4
     *
     */
    public function isMimetypeValid(string $mimetype): bool
    {
        return $this->getAllowedMimetypes()->contains($mimetype);
    }
}
