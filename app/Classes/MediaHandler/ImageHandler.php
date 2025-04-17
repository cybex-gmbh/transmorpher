<?php

namespace App\Classes\MediaHandler;

use App\Enums\ImageFormat;
use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Enums\ResponseState;
use App\Enums\Transformation;
use App\Models\Version;
use Optimize;
use Transform;

class ImageHandler extends OnDemandDerivativeMediaHandler
{
    protected MediaType $type = MediaType::IMAGE;
    protected MediaStorage $derivativesStorage = MediaStorage::IMAGE_DERIVATIVES;
    protected ResponseState $uploadSuccessful = ResponseState::IMAGE_UPLOAD_SUCCESSFUL;
    protected ResponseState $uploadFailed = ResponseState::CDN_INVALIDATION_FAILED;
    protected ResponseState $versionSetSuccessful = ResponseState::IMAGE_VERSION_SET;
    protected ResponseState $versionSetFailed = ResponseState::CDN_INVALIDATION_FAILED;

    /**
     * @return string
     */
    public function getValidationRules(): string
    {
        return sprintf('mimes:%s', implode(',', ImageFormat::getFormats()));
    }

    /**
     * @param Version $version
     * @param array|null $transformationsArray
     * @return false|string
     */
    public function applyTransformations(Version $version, ?array $transformationsArray): false|string
    {
        $derivative = Transform::transform($version->originalFilePath(), $transformationsArray);

        return Optimize::optimize($derivative, $transformationsArray[Transformation::QUALITY->value] ?? null);
    }
}
