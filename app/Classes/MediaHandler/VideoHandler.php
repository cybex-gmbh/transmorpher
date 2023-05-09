<?php

namespace App\Classes\MediaHandler;

use App\Enums\ResponseState;
use App\Helpers\Upload;
use App\Interfaces\MediaHandlerInterface;
use App\Models\Media;
use App\Models\UploadSlot;
use App\Models\User;
use App\Models\Version;
use CdnHelper;
use FilePathHelper;
use Throwable;
use Transcode;

class VideoHandler implements MediaHandlerInterface
{
    /**
     * @param string $basePath
     * @param UploadSlot $uploadSlot
     *
     * @param string $filePath
     * @param Media $media
     * @param Version $version
     *
     * @return ResponseState
     */
    public function handleSavedFile(string $basePath, UploadSlot $uploadSlot, string $filePath, Media $media, Version $version): ResponseState
    {
        $success = Transcode::createJob($filePath, $media, $version, $uploadSlot);

        return $success ? ResponseState::VIDEO_UPLOAD_SUCCESSFUL : ResponseState::DISPATCHING_TRANSCODING_JOB_FAILED;
    }

    /**
     * @return string
     */
    public function getValidationRules(): string
    {
        return 'mimetypes:video/x-msvideo,video/mpeg,video/ogg,video/webm,video/mp4';
    }

    /**
     * @param string $basePath
     * @return ResponseState|null
     */
    public function invalidateCdnCache(string $basePath): ResponseState|null
    {
        if (CdnHelper::isConfigured()) {
            try {
                CdnHelper::invalidateVideo($basePath);
            } catch (Throwable) {
                $responseState = ResponseState::CDN_INVALIDATION_FAILED;
            }
        }

        return $responseState ?? null;
    }

    /**
     * @param User $user
     * @param string $identifier
     * @param Media $media
     * @param Version $version
     * @param int $oldVersionNumber
     * @param bool $wasProcessed
     * @param string $callbackUrl
     * @return array
     */
    public function setVersion(User $user, string $identifier, Media $media, Version $version, int $oldVersionNumber, bool $wasProcessed, string $callbackUrl): array
    {
        if ($callbackUrl) {
            $filePath = FilePathHelper::toOriginalFile($user, $identifier, $version->number);

            $uploadSlot = Upload::createUploadSlot($user, $identifier, $callbackUrl);

            $success = Transcode::createJobForVersionUpdate($filePath, $media, $version, $uploadSlot, $oldVersionNumber, $wasProcessed);
            $responseState = $success ? ResponseState::VIDEO_UPLOAD_SUCCESSFUL : ResponseState::DISPATCHING_TRANSCODING_JOB_FAILED;
        } else {
            $responseState = ResponseState::NO_CALLBACK_URL_PROVIDED;
        }

        return [
            $responseState,
            $uploadSlot?->token
        ];
    }
}
