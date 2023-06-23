<?php

namespace App\Http\Controllers;

use App\Enums\ImageFormat;
use App\Enums\MediaStorage;
use App\Enums\Transformation;
use App\Helpers\Upload;
use App\Models\User;
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
     * @param string $identifier
     * @param string $transformations
     *
     * @return Application|ResponseFactory|Response
     */
    public function get(User $user, string $identifier, string $transformations = ''): Response|Application|ResponseFactory
    {
        $imageDerivativesDisk = MediaStorage::IMAGE_DERIVATIVES->getDisk();
        $transformationsArray = $this->getTransformations($transformations);
        $derivativePath = FilePathHelper::toImageDerivativeFile($user, $transformations, $identifier, $transformationsArray);

        // Check if derivative already exists and return if so.
        if (!config('transmorpher.dev_mode') && config('transmorpher.store_derivatives') && $imageDerivativesDisk->exists($derivativePath)) {
            $derivative = $imageDerivativesDisk->get($derivativePath);
        } else {
            $originalFilePath = FilePathHelper::toOriginalFile($user, $identifier);

            // Apply transformations to image.
            $derivative = Transform::transform($originalFilePath, $transformationsArray);
            $derivative = $this->optimizeDerivative($derivative, $transformationsArray[Transformation::QUALITY->value] ?? null);

            if (config('transmorpher.store_derivatives')) {
                $imageDerivativesDisk->put($derivativePath, $derivative);
            }
        }

        return response($derivative, 200, ['Content-Type' => $imageDerivativesDisk->mimeType($derivativePath)]);
    }

    /**
     * Retrieve an original image for a version.
     *
     * @param Request $request
     * @param string $identifier
     * @param int $versionNumber
     *
     * @return Application|Response|ResponseFactory
     */
    public function getVersion(Request $request, string $identifier, int $versionNumber): Response|Application|ResponseFactory
    {
        $originalsDisk = MediaStorage::ORIGINALS->getDisk();
        $pathToOriginal = FilePathHelper::toOriginalFile($request->user(), $identifier, $versionNumber);

        return response($originalsDisk->get($pathToOriginal), 200, ['Content-Type' => $originalsDisk->mimeType($pathToOriginal)]);
    }

    /**
     * Convert transformations request parameter to array.
     *
     * @param string $transformations
     *
     * @return array|null
     */
    protected function getTransformations(string $transformations): array|null
    {
        if (!$transformations) {
            return null;
        }

        $transformationsArray = null;
        $parameters = explode('+', $transformations);

        foreach ($parameters as $parameter) {
            [$key, $value] = explode('-', $parameter, 2);
            $transformationsArray[$key] = $value;
        }

        return $transformationsArray;
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
}
