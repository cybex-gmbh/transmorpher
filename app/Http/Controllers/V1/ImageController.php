<?php

namespace App\Http\Controllers\V1;

use App\Enums\ImageFormat;
use App\Enums\MediaStorage;
use App\Enums\Transformation;
use App\Http\Controllers\Controller;
use App\Exceptions\InvalidTransformationValueException;
use App\Exceptions\TransformationNotFoundException;
use App\Models\Media;
use App\Models\User;
use App\Models\Version;
use FilePathHelper;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Transform;

class ImageController extends Controller
{
    /**
     * Handles incoming image derivative requests.
     *
     * @param User $user
     * @param Media $media
     * @param string $transformations
     *
     * @return Application|ResponseFactory|Response
     */
    public function get(User $user, Media $media, string $transformations = ''): Response|Application|ResponseFactory
    {
        return $this->getDerivative($transformations, $media->currentVersion);
    }

    /**
     * Retrieve an original image for a version.
     *
     * @param Request $request
     * @param Media $media
     * @param Version $version
     * @return Application|Response|ResponseFactory
     */
    public function getOriginal(Request $request, Media $media, Version $version): Response|Application|ResponseFactory
    {
        $originalsDisk = MediaStorage::ORIGINALS->getDisk();
        $pathToOriginal = FilePathHelper::toOriginalFile($version);

        return response($originalsDisk->get($pathToOriginal), 200, ['Content-Type' => mime_content_type($originalsDisk->readStream($pathToOriginal))]);
    }

    /**
     * Retrieve a derivative for a version.
     *
     * @param Request $request
     * @param Media $media
     * @param Version $version
     * @param string $transformations
     * @return Response|Application|ResponseFactory
     */
    public function getDerivativeForVersion(Request $request, Media $media, Version $version, string $transformations = ''): Response|Application|ResponseFactory
    {
        return $this->getDerivative($transformations, $version);
    }

    /**
     * Optimize an image derivative.
     * Creates a temporary file since image optimizers only work locally.
     *
     * @param $derivative
     * @param int|null $quality
     * @return false|string
     */
    protected function optimizeDerivative($derivative, int $quality = null): string|false
    {
        // Temporary file is needed since optimizers only work locally.
        $tempFile = tempnam(sys_get_temp_dir(), 'transmorpher');
        file_put_contents($tempFile, $derivative);

        // Optimizes the image based on optimizers configured in 'config/image-optimizer.php'.
        ImageFormat::fromMimeType(mime_content_type($tempFile))->getOptimizer()->optimize($tempFile, $quality);

        $derivative = file_get_contents($tempFile);
        unlink($tempFile);

        return $derivative;
    }

    /**
     * @param string $transformations
     * @param Version $version
     * @return Application|ResponseFactory|Response
     */
    protected function getDerivative(string $transformations, Version $version): ResponseFactory|Application|Response
    {
        try {
            $transformationsArray = Transformation::arrayFromString($transformations);
        } catch (TransformationNotFoundException|InvalidTransformationValueException $exception) {
            abort(400, $exception->getMessage());
        }

        $imageDerivativesDisk = MediaStorage::IMAGE_DERIVATIVES->getDisk();
        $derivativePath = FilePathHelper::toImageDerivativeFile($version, $transformationsArray);

        // Check if derivative already exists and return if so.
        if (!config('transmorpher.dev_mode') && config('transmorpher.store_derivatives') && $imageDerivativesDisk->exists($derivativePath)) {
            $derivative = $imageDerivativesDisk->get($derivativePath);
        } else {
            $originalFilePath = FilePathHelper::toOriginalFile($version);

            // Apply transformations to image.
            $derivative = Transform::transform($originalFilePath, $transformationsArray);
            $derivative = $this->optimizeDerivative($derivative, $transformationsArray[Transformation::QUALITY->value] ?? null);

            if (config('transmorpher.store_derivatives')) {
                $imageDerivativesDisk->put($derivativePath, $derivative);
            }
        }

        return response($derivative, 200, ['Content-Type' => mime_content_type($imageDerivativesDisk->readStream($derivativePath))]);
    }
}
