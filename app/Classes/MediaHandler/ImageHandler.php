<?php

namespace App\Classes\MediaHandler;

use App\Enums\ImageFormat;
use App\Enums\ResponseState;
use App\Interfaces\MediaHandlerInterface;
use App\Models\Media;
use App\Models\UploadSlot;
use App\Models\Version;
use CdnHelper;
use Throwable;

class ImageHandler implements MediaHandlerInterface
{
    /**
     * @param string     $basePath
     * @param UploadSlot $uploadSlot
     * @param string     $filePath
     * @param Media      $media
     * @param Version    $version
     *
     * @return ResponseState
     */
    public function handleSavedFile(string $basePath, UploadSlot $uploadSlot, string $filePath, Media $media, Version $version): ResponseState
    {
        if (CdnHelper::isConfigured()) {
            try {
                CdnHelper::invalidateImage($basePath);
            } catch (Throwable) {
                $responseState = ResponseState::CDN_INVALIDATION_FAILED;
            }
        }

        // Only delete for image, since the UploadSlot will be needed inside the transcoding job.
        $uploadSlot->delete();

        return $responseState ?? ResponseState::IMAGE_UPLOAD_SUCCESSFUL;
    }

    /**
     * @return string
     */
    public function getValidationRules(): string
    {
        return sprintf('mimes:%s', implode(',', ImageFormat::getFormats()));
    }
}
