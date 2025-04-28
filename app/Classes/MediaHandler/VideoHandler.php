<?php

namespace App\Classes\MediaHandler;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Enums\ResponseState;
use App\Enums\UploadState;
use App\Models\Media;
use App\Models\UploadSlot;
use App\Models\User;
use App\Models\Version;
use BadMethodCallException;
use Transcode;

class VideoHandler extends MediaHandler
{
    protected MediaType $type = MediaType::VIDEO;
    protected MediaStorage $derivativesStorage = MediaStorage::VIDEO_DERIVATIVES;
    protected ResponseState $uploadSuccessful = ResponseState::VIDEO_UPLOAD_SUCCESSFUL;
    protected ResponseState $uploadFailed = ResponseState::TRANSCODING_JOB_DISPATCH_FAILED;
    protected ResponseState $versionSetSuccessful = ResponseState::VIDEO_VERSION_SET;
    protected ResponseState $versionSetFailed = ResponseState::TRANSCODING_JOB_DISPATCH_FAILED;

    /**
     * @param string $basePath
     * @param UploadSlot $uploadSlot
     * @param Version $version
     *
     * @return ResponseState
     */
    public function handleSavedFile(string $basePath, UploadSlot $uploadSlot, Version $version): ResponseState
    {
        \Log::info(sprintf('Dispatching transcoding job for media %s and version %s.', $version->Media->identifier, $version->getKey()));
        $success = Transcode::createJob($version, $uploadSlot);
        \Log::info(sprintf('Transcoding job dispatched with result %s for media %s and version %s.', $success, $version->Media->identifier, $version->getKey()));

        return $success ? $this->uploadSuccessful : $this->uploadFailed;
    }

    /**
     * @return string
     */
    public function getValidationRules(): string
    {
        return 'mimetypes:video/x-msvideo,video/mpeg,video/ogg,video/webm,video/mp4,video/x-matroska';
    }

    /**
     * This will create a new upload slot, so ongoing uploads/processings are aborted.
     *
     * @param User $user
     * @param Version $version
     * @return array
     */
    public function processVersion(User $user, Version $version): array
    {
        $uploadSlot = $user->UploadSlots()->withoutGlobalScopes()->updateOrCreate(['identifier' => $version->Media->identifier], ['media_type' => MediaType::VIDEO]);

        $success = Transcode::createJob($version, $uploadSlot);
        $responseState = $success ? $this->versionSetSuccessful : $this->versionSetFailed;

        return [
            $responseState,
            $uploadSlot?->token
        ];
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
    public function deleteDerivatives(): array
    {
        $failedMediaIds = [];

        foreach (Media::whereType(MediaType::VIDEO)->get() as $media) {
            // Restore latest version to (re-)generate derivatives.
            $version = $media->latestVersion;

            $version = $version->replicate()->fill([
                'number' => $media->latestVersion->number + 1,
                'processed' => 0,
            ]);
            $version->save();

            [$responseState] = $this->processVersion($media->User, $version);

            if ($responseState->getState() === UploadState::ERROR) {
                $failedMediaIds[] = $media->getKey();
            }
        }

        return [
            'success' => $success = !count($failedMediaIds),
            'message' => $success ? 'Restored versions for all video media.' : sprintf('Failed to restore versions for media ids: %s.', implode(', ', $failedMediaIds)),
        ];
    }

    /**
     * @param Version $version
     * @param array|null $transformationsArray
     * @return false|string
     */
    public function applyTransformations(Version $version, ?array $transformationsArray): false|string
    {
        throw new BadMethodCallException('Not yet applicable for this media type.');
    }
}
