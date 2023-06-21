<?php

namespace App\Enums;

enum ResponseState: string
{
    case CDN_INVALIDATION_FAILED = 'cdn_invalidation_failed';
    case DELETION_SUCCESSFUL = 'deletion_successful';
    case DISPATCHING_TRANSCODING_JOB_FAILED = 'dispatching_transcoding_job_failed';
    case IMAGE_UPLOAD_SUCCESSFUL = 'image_upload_successful';
    case IMAGE_VERSION_SET = 'image_version_set';
    case NO_CALLBACK_URL_PROVIDED = 'no_callback_url_provided';
    case TRANSCODING_ABORTED = 'transcoding_aborted';
    case TRANSCODING_FAILED = 'transcoding_failed';
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
            ResponseState::DELETION_SUCCESSFUL => UploadState::DELETED,
            ResponseState::IMAGE_UPLOAD_SUCCESSFUL,
            ResponseState::TRANSCODING_SUCCESSFUL,
            ResponseState::UPLOAD_SLOT_CREATED,
            ResponseState::VERSIONS_RETRIEVED,
            ResponseState::IMAGE_VERSION_SET => UploadState::SUCCESS,
            ResponseState::VIDEO_UPLOAD_SUCCESSFUL,
            ResponseState::VIDEO_VERSION_SET => UploadState::PROCESSING,
            default => UploadState::ERROR,
        };
    }

    /**
     * @return string
     */
    public function getResponse(): string
    {
        return trans(sprintf('responses.%s', $this->value));
    }
}
