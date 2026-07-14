<?php

namespace App\Enums;

enum ResponseState: string
{
    case CDN_INVALIDATION_FAILED = 'cdn.invalidation.failed';
    case DELETION_SUCCESSFUL = 'media.deletion.success';
    case DOCUMENT_UPLOAD_SUCCESSFUL = 'upload.document.success';
    case DOCUMENT_VERSION_SET = 'document.version.set.success';
    case IMAGE_UPLOAD_SUCCESSFUL = 'upload.image.success';
    case IMAGE_VERSION_SET = 'image.version.set.success';
    case TRANSCODING_ABORTED = 'video.transcoding.aborted';
    case TRANSCODING_FAILED = 'video.transcoding.failed';
    case TRANSCODING_JOB_DISPATCH_FAILED = 'video.transcoding.job_dispatch_failed';
    case TRANSCODING_SUCCESSFUL = 'video.transcoding.success';
    case UPLOAD_ABORTED = 'upload.aborted';
    case UPLOAD_SLOT_CREATED = 'upload.slot.created';
    case UPLOAD_SLOT_CREATION_FAILED = 'upload.slot.failed';
    case VERSIONS_RETRIEVED = 'media.versions.retrieved';
    case VIDEO_UPLOAD_SUCCESSFUL = 'upload.video.success';
    case VIDEO_VERSION_SET = 'video.version.set.success';
    case WRITE_FAILED = 'disk.write.failed';


    /**
     * @return UploadState
     */
    public function getState(): UploadState
    {
        return match ($this) {
            self::DELETION_SUCCESSFUL => UploadState::DELETED,
            self::IMAGE_UPLOAD_SUCCESSFUL,
            self::IMAGE_VERSION_SET,
            self::DOCUMENT_UPLOAD_SUCCESSFUL,
            self::DOCUMENT_VERSION_SET,
            self::TRANSCODING_SUCCESSFUL,
            self::VERSIONS_RETRIEVED => UploadState::SUCCESS,
            self::UPLOAD_ABORTED => UploadState::ABORTED,
            self::UPLOAD_SLOT_CREATED => UploadState::INITIALIZING,
            self::VIDEO_UPLOAD_SUCCESSFUL,
            self::VIDEO_VERSION_SET => UploadState::PROCESSING,
            default => UploadState::ERROR,
        };
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return trans(sprintf('responses.%s', $this->value));
    }

    public function getResponseCode(): int
    {
        return match ($this) {
            self::CDN_INVALIDATION_FAILED,
            self::WRITE_FAILED,
            self::UPLOAD_SLOT_CREATION_FAILED,
            self::TRANSCODING_FAILED,
            self::TRANSCODING_JOB_DISPATCH_FAILED => 500,
            self::DOCUMENT_UPLOAD_SUCCESSFUL,
            self::IMAGE_UPLOAD_SUCCESSFUL,
            self::VIDEO_UPLOAD_SUCCESSFUL => 201,
            default => 200,
        };
    }
}
