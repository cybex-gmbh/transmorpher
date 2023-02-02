<?php

namespace App\Interfaces;

use App\Models\Media;
use App\Models\Version;
use Illuminate\Contracts\Filesystem\Filesystem;

interface TranscoderInterface
{
    /**
     * @param string  $originalFilePath
     * @param Media   $media
     * @param Version $version
     * @param string  $callbackUrl
     * @param string  $idToken
     *
     * @return bool
     */
    public function createJob(string $originalFilePath, Media $media, Version $version, string $callbackUrl, string $idToken, Filesystem $disk): bool;

    /**
     * @param bool   $success
     * @param string $callbackUrl
     * @param string $idToken
     *
     * @return mixed
     */
    public function callback(bool $success, string $callbackUrl, string $idToken): mixed;
}
