<?php

namespace Tests\v2\Feature\Media\OnDemand\Document;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Enums\ResponseState;
use Tests\v2\Feature\Media\OnDemand\OnDemandMediaErrorTestCase;

class DocumentErrorTest extends OnDemandMediaErrorTestCase
{
    protected MediaType $mediaType = MediaType::DOCUMENT;
    protected MediaStorage $derivativesStorage = MediaStorage::DOCUMENT_DERIVATIVES;
    protected ResponseState $versionSetSuccessfulState = ResponseState::DOCUMENT_VERSION_SET;
    protected string $identifier = 'test-document-error';
    protected string $mediaFileFilePath = 'tests/data/test.pdf';
    protected string $invalidMimeFileFilePath = 'tests/data/test.png';
}

