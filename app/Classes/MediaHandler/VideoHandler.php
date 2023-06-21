<?php

namespace App\Classes\MediaHandler;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Enums\ResponseState;
use App\Helpers\Upload;
use App\Interfaces\MediaHandlerInterface;
use App\Models\Media;
use App\Models\UploadSlot;
use App\Models\User;
use App\Models\Version;
use CdnHelper;
use FilePathHelper;
use Illuminate\Contracts\Filesystem\Filesystem;
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
     * @return bool
     */
    public function invalidateCdnCache(string $basePath): bool
    {
        if (CdnHelper::isConfigured()) {
            try {
                CdnHelper::invalidateVideo($basePath);
            } catch (Throwable) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param User $user
     * @param Media $media
     * @param Version $version
     * @param int $oldVersionNumber
     * @param bool $wasProcessed
     * @param string $callbackUrl
     * @return array
     */
    public function setVersion(User $user, Media $media, Version $version, int $oldVersionNumber, bool $wasProcessed, string $callbackUrl): array
    {
        if ($callbackUrl) {
            $filePath = FilePathHelper::toOriginalFile($user, $media->identifier, $version->number);

            // Token and valid_until will be set in the 'saving' event.
            // By creating an upload slot, currently active uploading or transcoding will be canceled.
            $uploadSlot = $user->UploadSlots()->withoutGlobalScopes()->updateOrCreate(['identifier' => $media->identifier], ['callback_url' => $callbackUrl, 'media_type' => MediaType::VIDEO]);

            $success = Transcode::createJobForVersionUpdate($filePath, $media, $version, $uploadSlot, $oldVersionNumber, $wasProcessed);
            $responseState = $success ? ResponseState::VIDEO_VERSION_SET : ResponseState::DISPATCHING_TRANSCODING_JOB_FAILED;
        } else {
            $responseState = ResponseState::NO_CALLBACK_URL_PROVIDED;
        }

        return [
            $responseState,
            $uploadSlot?->token
        ];
    }

    /**
     * @return Filesystem
     */
    public function getDerivativesDisk(): Filesystem
    {
        return MediaStorage::VIDEO_DERIVATIVES->getDisk();
    }
}
