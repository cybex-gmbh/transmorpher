<?php

namespace App\Http\Controllers;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Http\Requests\ImageUploadRequest;
use App\Models\User;
use CdnHelper;
use Exception;
use FilePathHelper;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\LaravelImageOptimizer\Facades\ImageOptimizer;
use Transform;

class ImageController extends Controller
{
    /**
     * Handles incoming image upload requests.
     *
     * @param ImageUploadRequest $request
     *
     * @return JsonResponse
     */
    public function put(ImageUploadRequest $request): JsonResponse
    {
        $user          = $request->user();
        $imageFile     = $request->file('image');
        $identifier    = $request->get('identifier');
        $media         = $user->Media()->whereIdentifier($identifier)->firstOrCreate(['identifier' => $identifier, 'type' => MediaType::IMAGE]);
        $versionNumber = $media->Versions()->max('number') + 1;

        $basePath      = FilePathHelper::getBasePath($user, $identifier);
        $fileName      = FilePathHelper::createOriginalFileName($versionNumber, $imageFile->getClientOriginalName());
        $originalsDisk = MediaStorage::ORIGINALS->getDisk();

        $filePath = $originalsDisk->putFileAs($basePath, $imageFile, $fileName);
        $version  = $media->Versions()->create(['number'   => $versionNumber, 'filename' => $fileName]);

        $success = true;

        // Invalidate cache and delete entry if failed.
        if (CdnHelper::isConfigured()) {
            try {
                CdnHelper::invalidate(sprintf('/%s/*', MediaStorage::IMAGE_DERIVATIVES->getDisk()->path($basePath)));
            } catch (Exception) {
                $originalsDisk->delete($filePath);
                $version->delete();

                $success       = false;
                $versionNumber -= 1;
            }
        }

        // Todo: to ensure that failed uploads don't pollute the image derivative cache, we would need a ready flag that is set to true when CDN is invalidated.

        return response()->json([
            'success'    => $success,
            'response'   => $success ? 'Successfully added new image version.' : 'Cache invalidation failed.',
            'identifier' => $media->identifier,
            'version'    => $versionNumber,
            'client'     => $user->name,
        ], 201);
    }

    /**
     * Handles incoming image derivative requests.
     *
     * @param User   $user
     * @param string $identifier
     * @param string $transformations
     *
     * @return Application|ResponseFactory|Response
     */
    public function get(User $user, string $identifier, string $transformations = ''): Response|Application|ResponseFactory
    {
        $imageDerivativesDisk = MediaStorage::IMAGE_DERIVATIVES->getDisk();
        $transformationsArray = $this->getTransformations($transformations);
        $derivativePath       = FilePathHelper::getPathToImageDerivative($user, $transformations, $identifier, $transformationsArray);

        // Check if derivative already exists and return if so.
        if (!config('transmorpher.dev_mode') && config('transmorpher.store_derivatives') && $imageDerivativesDisk->exists($derivativePath)) {
            $derivative = $imageDerivativesDisk->get($derivativePath);
        } else {
            $originalFilePath = FilePathHelper::getPathToOriginal($user, $identifier);

            // Apply transformations to image.
            $derivative = Transform::transform($originalFilePath, $transformationsArray);
            $derivative = $this->optimizeDerivative($derivative);

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
     * @param string  $identifier
     * @param int     $versionNumber
     *
     * @return Application|Response|ResponseFactory
     */
    public function getVersion(Request $request, string $identifier, int $versionNumber): Response|Application|ResponseFactory
    {
        $originalsDisk = MediaStorage::ORIGINALS->getDisk();
        $pathToOriginal = FilePathHelper::getPathToOriginal($request->user(), $identifier, $versionNumber);

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
        $parameters           = explode('+', $transformations);

        foreach ($parameters as $parameter) {
            [$key, $value] = explode('-', $parameter);
            $transformationsArray[$key] = $value;
        }

        return $transformationsArray;
    }

    /**
     * Optimize an image derivative.
     * Creates a temporary file since image optimizers only work locally.
     *
     * @param $derivative
     *
     * @return false|string
     */
    protected function optimizeDerivative($derivative): string|false
    {
        // Temporary file is needed since optimizers only work locally.
        $tempFile = tempnam(sys_get_temp_dir(), 'transmorpher');
        file_put_contents($tempFile, $derivative);

        // Optimizes the image based on optimizers configured in 'config/image-optimizer.php'.
        ImageOptimizer::optimize($tempFile);

        $derivative = file_get_contents($tempFile);
        unlink($tempFile);

        return $derivative;
    }
}
