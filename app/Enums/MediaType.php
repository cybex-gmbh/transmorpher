<?php

namespace App\Enums;

use App\Models\Media;
use App\Models\UploadSlot;
use App\Models\Version;
use CdnHelper;
use Throwable;
use Transcode;

enum MediaType: string
{
    case IMAGE = 'image';
    case VIDEO = 'video';

    public function getValidationRules(): string
    {
        return match ($this) {
            MediaType::IMAGE => sprintf('mimes:%s', implode(',', ImageFormat::getFormats())),
            MediaType::VIDEO => 'mimetypes:video/x-msvideo,video/mpeg,video/ogg,video/webm,video/mp4'
        };
    }

    public function getUploadResponseMessage(): string
    {
        return match ($this) {
            MediaType::IMAGE => 'Successfully added new image version.',
            MediaType::VIDEO => 'Successfully uploaded video, transcoding job has been dispatched.'
        };
    }

    public function handleSavedFile(string $basePath, UploadSlot $uploadSlot, string $filePath, Media $media, Version $version): ResponseState
    {
        return match ($this) {
            MediaType::IMAGE => $this->handleSavedImage($basePath, $uploadSlot),
            MediaType::VIDEO => $this->handleSavedVideo($filePath, $media, $version, $uploadSlot)
        };
    }

    protected function handleSavedImage(string $basePath, UploadSlot $uploadSlot): ResponseState
    {
        if (CdnHelper::isConfigured()) {
            try {
                CdnHelper::invalidateImage($basePath);
            } catch (Throwable) {
                $responseState = ResponseState::CACHE_INVALIDATION_FAILED;
            }
        }

        // Only delete for image, since the UploadSlot will be needed inside the transcoding job.
        $uploadSlot->delete();

        return $responseState ?? ResponseState::IMAGE_UPLOAD_SUCCESSFUL;
    }

    protected function handleSavedVideo(string $filePath, Media $media, Version $version, UploadSlot $uploadSlot): ResponseState
    {
        $success = Transcode::createJob($filePath, $media, $version, $uploadSlot);

        return $success ? ResponseState::VIDEO_UPLOAD_SUCCESSFUL : ResponseState::DISPATCHING_TRANSCODING_JOB_FAILED;
    }
}
