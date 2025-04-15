<?php

namespace App\Enums;

enum ResponseState: string
{
    case CDN_INVALIDATION_FAILED = 'cdn_invalidation_failed';
    case DELETION_SUCCESSFUL = 'deletion_successful';
    case IMAGE_UPLOAD_SUCCESSFUL = 'image_upload_successful';
    case IMAGE_VERSION_SET = 'image_version_set';
    case DOCUMENT_UPLOAD_SUCCESSFUL = 'document_upload_successful';
    case DOCUMENT_VERSION_SET = 'document_version_set';
    case TRANSCODING_ABORTED = 'transcoding_aborted';
    case TRANSCODING_FAILED = 'transcoding_failed';
    case TRANSCODING_JOB_DISPATCH_FAILED = 'transcoding_job_dispatch_failed';
    case TRANSCODING_SUCCESSFUL = 'transcoding_successful';
    case UPLOAD_SLOT_CREATED = 'upload_slot_created';
    case VERSIONS_RETRIEVED = 'versions_retrieved';
    case VIDEO_UPLOAD_SUCCESSFUL = 'video_upload_successful';
    case VIDEO_VERSION_SET = 'video_version_set';
    case WRITE_FAILED = 'write_failed';


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
}
