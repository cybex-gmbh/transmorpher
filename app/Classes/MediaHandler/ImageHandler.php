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
     * @deprecated Should be renamed after v1 is removed. A more suitable name would be getAllowedMimetypes()
     */
    public function getValidationRules(): string
    {
        return sprintf('mimes:%s', implode(',', ImageFormat::getFormats()));
    }

    /**
     * @inheritDoc
     */
    public function isMimeTypeValid(string $mimeType): bool
    {
        return parent::isMimeTypeValid(ImageFormat::tryFromMimeType($mimeType)?->value ?? '');
    }

    /**
     * @param Version $version
     * @param array|null $transformationsArray
     * @return false|string
     */
    public function applyTransformations(Version $version, ?array $transformationsArray): false|string
    {
        $derivativeFileData = Transform::transform($version->originalFilePath(), $transformationsArray);

        return Optimize::optimize($derivativeFileData, $transformationsArray[Transformation::QUALITY->value] ?? null);
    }
}
