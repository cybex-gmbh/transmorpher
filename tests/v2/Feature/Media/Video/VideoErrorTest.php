<?php

namespace Tests\v2\Feature\Media\Video;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Enums\ResponseState;
use Tests\v2\Feature\Media\MediaErrorTestCase;

class VideoErrorTest extends MediaErrorTestCase
{
    protected MediaType $mediaType = MediaType::VIDEO;
    protected MediaStorage $derivativesStorage = MediaStorage::VIDEO_DERIVATIVES;
    protected ResponseState $versionSetSuccessfulState = ResponseState::VIDEO_VERSION_SET;
    protected string $identifier = 'test-video-error';
    protected string $mediaFileFilePath = 'tests/data/test.mp4';
    protected string $invalidMimeFileFilePath = 'tests/data/test.png';
}

