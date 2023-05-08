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

    public function handleSavedFile(string $basePath, UploadSlot $uploadSlot, string $filePath, Media $media, Version $version): array
    {
        return match ($this) {
            MediaType::IMAGE => $this->handleSavedImage($basePath, $uploadSlot),
            MediaType::VIDEO => $this->handleSavedVideo($filePath, $media, $version, $uploadSlot)
        };
    }

    protected function handleSavedImage(string $basePath, UploadSlot $uploadSlot): array
    {
        if (CdnHelper::isConfigured()) {
            try {
                CdnHelper::invalidateImage($basePath);
            } catch (Throwable) {
                $success = false;
                $response = 'Cache invalidation failed.';
            }
        }

        // Only delete for image, since the UploadSlot will be needed inside the transcoding job.
        $uploadSlot->delete();

        return [
            'success' => $success ?? true,
            'response' => $response ?? $this->getUploadResponseMessage()
        ];
    }

    protected function handleSavedVideo(string $filePath, Media $media, Version $version, UploadSlot $uploadSlot): array
    {
        $success = Transcode::createJob($filePath, $media, $version, $uploadSlot);

        return [
            'success' => $success,
            'response' => $success ? $this->getUploadResponseMessage() : 'There was an error when trying to dispatch the transcoding job.'
        ];
    }
}
