<?php

namespace App\Classes;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Enums\Transformation;
use App\Exceptions\InvalidTransformationFormatException;
use App\Exceptions\InvalidTransformationValueException;
use App\Exceptions\TransformationNotFoundException;
use App\Models\Version;
use finfo;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;

class Delivery
{
    /**
     * Retrieve an original for a version.
     *
     * @param Version $version
     * @return Application|Response|ResponseFactory
     */
    public function getOriginal(Version $version): Response|Application|ResponseFactory
    {
        $originalsDisk = MediaStorage::ORIGINALS->getDisk();
        $pathToOriginal = $version->originalFilePath();

        return response($originalsDisk->get($pathToOriginal), 200, ['Content-Type' => mime_content_type($originalsDisk->readStream($pathToOriginal))]);
    }

    /**
     * @param string $transformations
     * @param Version $version
     * @param MediaType $mediaType
     * @return Application|ResponseFactory|Response
     */
    public function getDerivative(string $transformations, Version $version, MediaType $mediaType): ResponseFactory|Application|Response
    {
        try {
            $transformationsArray = Transformation::arrayFromString($transformations);
        } catch (TransformationNotFoundException|InvalidTransformationValueException|InvalidTransformationFormatException $exception) {
            abort(400, $exception->getMessage());
        }

        $derivativesDisk = $mediaType->handler()->getDerivativesDisk();
        $derivativePath = $version->onDemandDerivativeFilePath($transformationsArray);
        $finfo = new finfo(FILEINFO_MIME_TYPE);

        // Check if derivative already exists and return if so.
        if (!config('transmorpher.dev_mode') && config('transmorpher.store_derivatives') && $derivativesDisk->exists($derivativePath)) {
            $derivative = $derivativesDisk->get($derivativePath);
        } else {
            // Apply transformations to the media.
            $derivative = $mediaType->handler()->applyTransformations($version, $transformationsArray);

            if (config('transmorpher.store_derivatives')) {
                $derivativesDisk->put($derivativePath, $derivative);
            }
        }

        return response($derivative, 200, ['Content-Type' => $finfo->buffer($derivative)]);
    }
}
