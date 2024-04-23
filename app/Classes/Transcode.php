<?php

namespace App\Classes;

use App\Enums\ClientNotification;
use App\Enums\MediaType;
use App\Enums\ResponseState;
use App\Helpers\SodiumHelper;
use App\Interfaces\TranscodeInterface;
use App\Jobs\TranscodeVideo;
use App\Models\Media;
use App\Models\UploadSlot;
use App\Models\Version;
use Exception;
use Http;

class Transcode implements TranscodeInterface
{
    /**
     * Creates a job which handles the transcoding of a video.
     *
     * @param Version $version
     * @param UploadSlot $uploadSlot
     * @return bool
     */
    public function createJob(Version $version, UploadSlot $uploadSlot): bool
    {
        /*
        * When using SQS FIFO:
        * Messages in a SQS FIFO queue are processed in order per message group id.
        * To ensure parallel processing those message group ids should be unique.
        * See SqsFifoQueue class.
        */
        try {
            TranscodeVideo::dispatch($version, $uploadSlot);
        } catch (Exception) {
            return false;
        }

        return true;
    }

    /**
     * Creates a job which handles the transcoding of a video when a version number is updated.
     *
     * @param Version $version
     * @param UploadSlot $uploadSlot
     * @param int $oldVersionNumber
     * @param bool $wasProcessed
     *
     * @return bool
     */
    public function createJobForVersionUpdate(Version $version, UploadSlot $uploadSlot, int $oldVersionNumber, bool $wasProcessed): bool
    {
        try {
            TranscodeVideo::dispatch($version, $uploadSlot, $oldVersionNumber, $wasProcessed);
        } catch (Exception) {
            return false;
        }

        return true;
    }

    /**
     * Inform client package about the transcoding result.
     *
     * @param ResponseState $responseState
     * @param string $uploadToken
     * @param Media $media
     * @param int $versionNumber
     *
     * @return void
     */
    public function callback(ResponseState $responseState, string $uploadToken, Media $media, int $versionNumber): void
    {
        $notification = [
            'state' => $responseState->getState()->value,
            'message' => $responseState->getMessage(),
            'identifier' => $media->identifier,
            'version' => $versionNumber,
            'upload_token' => $uploadToken,
            'public_path' => implode(DIRECTORY_SEPARATOR, array_filter([MediaType::VIDEO->prefix(), $media->baseDirectory()])),
            'hash' => Version::whereNumber($versionNumber)->first()?->hash,
            'notification_type' => ClientNotification::VIDEO_TRANSCODING->value,
        ];

        $signedNotification = SodiumHelper::sign(json_encode($notification));

        Http::post($media->User->api_url, ['signed_notification' => $signedNotification]);
    }
}
