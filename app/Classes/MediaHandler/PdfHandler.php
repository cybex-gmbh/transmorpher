<?php

namespace App\Classes\MediaHandler;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Enums\ResponseState;
use App\Enums\Transformation;
use App\Exceptions\PdfPageDoesNotExistException;
use App\Models\Version;
use Optimize;
use Transform;

class PdfHandler extends StaticMediaHandler
{
    protected MediaType $type = MediaType::PDF;
    protected MediaStorage $derivativesStorage = MediaStorage::PDF_DERIVATIVES;
    protected ResponseState $uploadSuccessful = ResponseState::PDF_UPLOAD_SUCCESSFUL;
    protected ResponseState $uploadFailed = ResponseState::CDN_INVALIDATION_FAILED;
    protected ResponseState $versionSetSuccessful = ResponseState::PDF_VERSION_SET;
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
        if ($transformationsArray) {
            try {
                $derivative = Transform::transform($version->originalFilePath(), $transformationsArray);
            } catch (PdfPageDoesNotExistException $exception) {
                abort(400, $exception->getMessage());
            }

            return Optimize::optimize($derivative, $transformationsArray[Transformation::QUALITY->value] ?? null);
        }

        return Optimize::removePdfMetadata(MediaStorage::ORIGINALS->getDisk()->get($version->originalFilePath()));
    }
}
