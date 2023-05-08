<?php

namespace App\Classes\MediaHandler;

use App\Enums\ResponseState;
use App\Interfaces\MediaHandlerInterface;
use App\Models\Media;
use App\Models\UploadSlot;
use App\Models\Version;
use Transcode;

class VideoHandler implements MediaHandlerInterface
{
    /**
     * @param string     $basePath
     * @param UploadSlot $uploadSlot
     *
     * @param string     $filePath
     * @param Media      $media
     * @param Version    $version
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
}
