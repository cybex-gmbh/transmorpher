<?php

namespace App\Classes;

use App\Models\UploadSlot;
use App\Models\User;
use FilePathHelper;
use App\Helpers\SigningHelper;
use App\Interfaces\TranscodeInterface;
use App\Jobs\TranscodeVideo;
use App\Models\Media;
use App\Models\Version;
use Exception;
use Http;

class Transcode implements TranscodeInterface
{
    /**
     * Creates a job which handles the transcoding of a video.
     *
     * @param string $originalFilePath
     * @param Media $media
     * @param Version $version
     * @param UploadSlot $uploadSlot
     * @return bool
     */
    public function createJob(string $originalFilePath, Media $media, Version $version, UploadSlot $uploadSlot): bool
    {
        /*
        * When using SQS FIFO:
        * Messages in a SQS FIFO queue are processed in order per message group id.
        * To ensure parallel processing those message group ids should be unique.
        * See SqsFifoQueue class.
        */
        try {
            TranscodeVideo::dispatch($originalFilePath, $media, $version, $uploadSlot);
        } catch (Exception) {
            return false;
        }

        return true;
    }

    /**
     * Creates a job which handles the transcoding of a video when a version number is updated.
     *
     * @param string $originalFilePath
     * @param Media $media
     * @param Version $version
     * @param UploadSlot $uploadSlot
     * @param int $oldVersionNumber
     * @param bool $wasProcessed
     *
     * @return bool
     */
    public function createJobForVersionUpdate(string $originalFilePath, Media $media, Version $version, UploadSlot $uploadSlot, int $oldVersionNumber, bool $wasProcessed): bool
    {
        try {
            TranscodeVideo::dispatch($originalFilePath, $media, $version, $uploadSlot, $oldVersionNumber, $wasProcessed);
        } catch (Exception) {
            return false;
        }

        return true;
    }

    /**
     * Inform client package about the transcoding result.
     *
     * @param bool $success
     * @param string $callbackUrl
     * @param string $callbackToken
     * @param User $user
     * @param string $identifier
     * @param int $versionNumber
     *
     * @return void
     */
    public function callback(bool $success, string $callbackUrl, string $callbackToken, User $user, string $identifier, int $versionNumber): void
    {
        $response = [
            'success' => $success,
            'response' => $success ? 'Successfully transcoded video.' : 'Video transcoding failed.',
            'identifier' => $identifier,
            'version' => $versionNumber,
            'client' => $user->name,
            'callback_token' => $callbackToken,
            'public_path' => sprintf('derivative-videos/%s', FilePathHelper::toBaseDirectory($user, $identifier)),
        ];

        $signedResponse = SigningHelper::sign(json_encode($response));

        Http::post($callbackUrl, ['signed_response' => $signedResponse]);
    }
}
