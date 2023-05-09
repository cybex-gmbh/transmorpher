<?php

namespace App\Enums;

enum ResponseState: string
{
    case CDN_INVALIDATION_FAILED = 'CDN invalidation failed.';
    case DELETION_SUCCESSFUL = 'Successfully deleted media.';
    case DISPATCHING_TRANSCODING_JOB_FAILED = 'There was an error when trying to dispatch the transcoding job.';
    case IMAGE_UPLOAD_SUCCESSFUL = 'Successfully uploaded new image version.';
    case NO_CALLBACK_URL_PROVIDED = 'A callback URL is needed for this identifier.';
    case TRANSCODING_ABORTED = 'Transcoding process aborted due to a new version or upload.';
    case TRANSCODING_FAILED = 'Video transcoding failed, version has been removed.';
    case TRANSCODING_SUCCESSFUL = 'Successfully transcoded video.';
    case UPLOAD_SLOT_CREATED = 'Successfully created upload slot.';
    case VERSIONS_RETRIEVED = 'Successfully retrieved version numbers.';
    case VERSION_SET = 'Successfully set media version.';
    case VIDEO_UPLOAD_SUCCESSFUL = 'Successfully uploaded new video version, transcoding job has been dispatched.';
    case WRITE_FAILED = 'Could not write media to disk.';


    /**
     * @return bool
     */
    public function success(): bool
    {
        return match ($this) {
            ResponseState::DELETION_SUCCESSFUL,
            ResponseState::IMAGE_UPLOAD_SUCCESSFUL,
            ResponseState::TRANSCODING_SUCCESSFUL,
            ResponseState::UPLOAD_SLOT_CREATED,
            ResponseState::VERSIONS_RETRIEVED,
            ResponseState::VERSION_SET,
            ResponseState::VIDEO_UPLOAD_SUCCESSFUL => true,
            default => false
        };
    }
}
