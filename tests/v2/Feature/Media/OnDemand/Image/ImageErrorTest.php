<?php

namespace Tests\v2\Feature\Media\OnDemand\Image;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Enums\ResponseState;
use Tests\v2\Support\IsOnDemandMedia;
use Tests\v2\Support\MediaHelper;

class ImageErrorTest extends MediaHelper
{
	use IsOnDemandMedia;

	protected MediaType $mediaType = MediaType::IMAGE;
	protected MediaStorage $derivativesStorage = MediaStorage::IMAGE_DERIVATIVES;
	protected ResponseState $versionSetSuccessfulState = ResponseState::IMAGE_VERSION_SET;
	protected string $identifier = 'test-image-error';
	protected string $mediaFileFilePath = 'tests/data/test.png';
}

