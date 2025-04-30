<?php

namespace App\Classes\MediaHandler;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Enums\ResponseState;
use App\Enums\Transformation;
use App\Models\Version;
use Optimize;
use Transform;

class DocumentHandler extends OnDemandDerivativeMediaHandler
{
    protected MediaType $type = MediaType::DOCUMENT;
    protected MediaStorage $derivativesStorage = MediaStorage::DOCUMENT_DERIVATIVES;
    protected ResponseState $uploadSuccessful = ResponseState::DOCUMENT_UPLOAD_SUCCESSFUL;
    protected ResponseState $uploadFailed = ResponseState::CDN_INVALIDATION_FAILED;
    protected ResponseState $versionSetSuccessful = ResponseState::DOCUMENT_VERSION_SET;
    protected ResponseState $versionSetFailed = ResponseState::CDN_INVALIDATION_FAILED;

    /**
     * @return string
     */
    public function getValidationRules(): string
    {
        return 'mimetypes:application/pdf';
    }

    /**
     * @param Version $version
     * @param array|null $transformationsArray
     * @return string
     */
    public function applyTransformations(Version $version, ?array $transformationsArray): string
    {
        if ($transformationsArray[Transformation::FORMAT->value] ?? false) {
            $derivativeFileData = Transform::transform($version->originalFilePath(), $transformationsArray);

            return Optimize::optimize($derivativeFileData, $transformationsArray[Transformation::QUALITY->value] ?? null);
        }

        return Optimize::removeDocumentMetadata(MediaStorage::ORIGINALS->getDisk()->get($version->originalFilePath()));
    }
}
