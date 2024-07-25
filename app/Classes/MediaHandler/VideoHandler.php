<?php

namespace App\Classes\MediaHandler;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Enums\ResponseState;
use App\Enums\UploadState;
use App\Interfaces\MediaHandlerInterface;
use App\Models\Media;
use App\Models\UploadSlot;
use App\Models\User;
use App\Models\Version;
use CdnHelper;
use Illuminate\Contracts\Filesystem\Filesystem;
use Throwable;
use Transcode;

class VideoHandler implements MediaHandlerInterface
{
    /**
     * @param string $basePath
     * @param UploadSlot $uploadSlot
     * @param Version $version
     *
     * @return ResponseState
     */
    public function handleSavedFile(string $basePath, UploadSlot $uploadSlot, Version $version): ResponseState
    {
        \Log::info('Dispatching transcoding job...');
        $success = Transcode::createJob($version, $uploadSlot);
        \Log::info(sprintf('Transcoding job dispatched with result: %s.', $success));

        return $success ? ResponseState::VIDEO_UPLOAD_SUCCESSFUL : ResponseState::TRANSCODING_JOB_DISPATCH_FAILED;
    }

    /**
     * @return string
     */
    public function getValidationRules(): string
    {
        return 'mimetypes:video/x-msvideo,video/mpeg,video/ogg,video/webm,video/mp4,video/x-matroska';
    }

    /**
     * @param string $basePath
     * @return bool
     */
    public function invalidateCdnCache(string $basePath): bool
    {
        if (CdnHelper::isConfigured()) {
            try {
                CdnHelper::invalidateMedia(MediaType::VIDEO, $basePath);
            } catch (Throwable) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param User $user
     * @param Version $version
     * @param int $oldVersionNumber
     * @param bool $wasProcessed
     * @return array
     */
    public function setVersion(User $user, Version $version, int $oldVersionNumber, bool $wasProcessed): array
    {
        // Token and valid_until will be set in the 'saving' event.
        // By creating an upload slot, currently active uploading or transcoding will be canceled.
        $uploadSlot = $user->UploadSlots()->withoutGlobalScopes()->updateOrCreate(['identifier' => $version->Media->identifier], ['media_type' => MediaType::VIDEO]);

        $success = Transcode::createJobForVersionUpdate($version, $uploadSlot, $oldVersionNumber, $wasProcessed);
        $responseState = $success ? ResponseState::VIDEO_VERSION_SET : ResponseState::TRANSCODING_JOB_DISPATCH_FAILED;

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

    /**
     * @param Media $media
     * @return array
     */
    public function getVersions(Media $media): array
    {
        $versions = $media->Versions;

        return [
            'currentVersion' => $versions->max('number'),
            'currentlyProcessedVersion' => $versions->where('processed', true)->max('number'),
            'versions' => $versions->pluck('created_at', 'number')->map(fn($date) => strtotime($date)),
        ];
    }

    /**
     * @return array
     */
    public function purgeDerivatives(): array
    {
        $failedMediaIds = [];

        foreach (Media::whereType(MediaType::VIDEO)->get() as $media) {
            // Restore latest version to (re-)generate derivatives.
            $version = $media->latestVersion;

            $oldVersionNumber = $version->number;
            $wasProcessed = $version->processed;

            $version->update(['number' => $media->latestVersion->number + 1, 'processed' => 0]);
            [$responseState, $uploadToken] = $this->setVersion($media->User, $version, $oldVersionNumber, $wasProcessed);

            if ($responseState->getState() === UploadState::ERROR) {
                $failedMediaIds[] = $media->getKey();
            }
        }

        return [
            'success' => $success = !count($failedMediaIds),
            'message' => $success ? 'Restored versions for all video media.' : sprintf('Failed to restore versions for media ids: %s.', implode(', ', $failedMediaIds)),
        ];
    }
}
