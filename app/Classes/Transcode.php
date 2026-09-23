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
use Http;
use Throwable;

class Transcode implements TranscodeInterface
{
    /**
     * Returns the class which handles the actual transcoding.
     *
     * @return string
     */
    public function getJobClass(): string
    {
        return TranscodeVideo::class;
    }

    /**
     * Creates a job which handles the transcoding of a video.
     *
     * @param Version $version
     * @param UploadSlot $uploadSlot
     * @return bool
     */
    public function createJob(Version $version, UploadSlot $uploadSlot): bool
    {
        try {
            TranscodeVideo::dispatch($version, $uploadSlot);
        } catch (Throwable $throwable) {
            report($throwable);

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

        \Log::info(sprintf('Sending signed notification with state %s to client package for media %s and version %s, message: %s', $responseState->getState()->value, $media->identifier, Version::whereNumber($versionNumber)->first()?->getKey(), $responseState->getMessage()));
        Http::post($media->User->api_url, ['signed_notification' => $signedNotification]);
    }
}
