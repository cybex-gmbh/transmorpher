<?php

namespace App\Classes;

use App\Interfaces\TranscoderInterface;
use App\Jobs\TranscodeVideo;
use App\Models\Media;
use App\Models\Version;
use Illuminate\Contracts\Filesystem\Filesystem;

class Transcoder implements TranscoderInterface
{

    public function createJob(string $originalFilePath, Media $media, Version $version, string $callbackUrl, string $idToken, Filesystem $disk): bool
    {
        TranscodeVideo::dispatch($originalFilePath, $media, $version, $callbackUrl, $idToken, $disk);

        // TODO Can this fail?

        return true;
    }

    public function callback(bool $success, string $callbackUrl, string $idToken): mixed
    {
        // TODO Implement this with more information (such as version, identifier, client).

        return [
            'success'  => $success,
            'response' => $success ? "Successfully transcoded video." : 'Video transcoding failed.',
        ];
    }
}
