<?php

namespace Tests\v2\Feature\Media\OnDemand\Image;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Enums\ResponseState;
use Tests\v2\Feature\Media\OnDemand\OnDemandMediaTestCase;

class ImageTest extends OnDemandMediaTestCase
{
    protected MediaType $mediaType = MediaType::IMAGE;
    protected MediaStorage $derivativesStorage = MediaStorage::IMAGE_DERIVATIVES;
    protected ResponseState $versionSetSuccessfulState = ResponseState::IMAGE_VERSION_SET;
    protected string $identifier = 'test-image';
    protected string $mediaFileFilePath = 'tests/data/test.png';
    protected string $originalContentType = 'image/png';
    protected string $derivativeContentType = 'image/png';
}
