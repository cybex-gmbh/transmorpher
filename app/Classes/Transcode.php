<?php

namespace App\Classes;

use App\Helpers\SodiumHelper;
use App\Interfaces\TranscodeInterface;
use App\Jobs\TranscodeVideo;
use App\Models\Media;
use App\Models\Version;
use Http;
use Illuminate\Contracts\Filesystem\Filesystem;

class Transcode implements TranscodeInterface
{

    /**
     * Creates a job which handles the transcoding of a video.
     *
     * @param string     $originalFilePath
     * @param Media      $media
     * @param Version    $version
     * @param string     $callbackUrl
     * @param string     $idToken
     * @param Filesystem $disk
     *
     * @return bool
     */
    public function createJob(string $originalFilePath, Media $media, Version $version, string $callbackUrl, string $idToken, Filesystem $disk): bool
    {
        TranscodeVideo::dispatch($originalFilePath, $media, $version, $callbackUrl, $idToken, $disk);

        return true;
    }

    /**
     * Inform client package about the transcoding result.
     *
     * @param bool   $success
     * @param string $callbackUrl
     * @param string $idToken
     * @param string $userName
     * @param string $identifier
     * @param int    $versionNumber
     *
     * @return void
     */
    public function callback(bool $success, string $callbackUrl, string $idToken, string $userName, string $identifier, int $versionNumber): void
    {
        $response = [
            'success'    => $success,
            'response'   => $success ? "Successfully transcoded video." : 'Video transcoding failed.',
            'identifier' => $identifier,
            'version'    => $versionNumber,
            'client'     => $userName,
            'id_token'   => $idToken,
        ];

        $signedResponse = SodiumHelper::sign(json_encode($response));

        Http::post($callbackUrl, [$signedResponse]);
    }
}
