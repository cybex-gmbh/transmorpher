<?php

namespace App\Http\Controllers;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use CdnHelper;
use App\Http\Requests\ImageUploadRequest;
use App\Models\User;
use Exception;
use FilePathHelper;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
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
        $user       = $request->user();
        $imageFile  = $request->file('image');
        $identifier = $request->get('identifier');
        $disk       = MediaStorage::ORIGINALS->getDisk();

        // Save image to disk and create database entry.
        $response = $this->saveImage($imageFile, $user, $identifier, $disk);

        return response()->json($response, 201);
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
        $diskImageDerivatives = MediaStorage::IMAGE_DERIVATIVES->getDisk();
        $transformationsArray = $this->getTransformations($transformations);
        $derivativePath       = FilePathHelper::getImageDerivativePath($user, $transformations, $identifier, $transformationsArray);

        // Check if derivative already exists and return if so.
        if (!config('transmorpher.dev_mode') && config('transmorpher.store_derivatives') && $diskImageDerivatives->exists($derivativePath)) {
            $derivative = $diskImageDerivatives->get($derivativePath);
        } else {
            $originalFilePath = FilePathHelper::getImageOriginalPath($user, $identifier);

            // Apply transformations to image.
            $derivative = Transform::transmorph($originalFilePath, $transformationsArray);
            $derivative = $this->optimizeDerivative($derivative);

            if (config('transmorpher.store_derivatives')) {
                $diskImageDerivatives->put($derivativePath, $derivative);
            }
        }

        return response($derivative, 200, ['Content-Type' => $diskImageDerivatives->mimeType($derivativePath)]);
    }

    /**
     * Saves an uploaded image to the specified disk.
     * Creates a new version for the identifier in the database.
     *
     * @param UploadedFile      $imageFile
     * @param User              $user
     * @param string            $identifier
     * @param FilesystemAdapter $disk
     *
     * @return array
     */
    protected function saveImage(UploadedFile $imageFile, User $user, string $identifier, Filesystem $disk): array
    {
        $media         = $user->Media()->whereIdentifier($identifier)->firstOrCreate(['identifier' => $identifier, 'type' => MediaType::IMAGE]);
        $versionNumber = $media->Versions()->max('number') + 1;
        $basePath      = FilePathHelper::getOriginalsBasePath($user, $identifier);
        $fileName      = FilePathHelper::createOriginalFileName($versionNumber, $imageFile->getClientOriginalName());

        $filePath = $disk->putFileAs($basePath, $imageFile, $fileName);
        $version  = $media->Versions()->create(['number' => $versionNumber, 'filename' => $fileName]);

        $success = true;

        // Invalidate cache and delete entry if failed.
        if (CdnHelper::isConfigured()) {
            try {
                CdnHelper::invalidate(sprintf('/%s/*', MediaStorage::IMAGE_DERIVATIVES->getDisk()->path($basePath)));
            } catch (Exception) {
                $disk->delete($filePath);
                $version->delete();

                $success = false;
                $versionNumber -= 1;
            }
        }

        return [
            'success'    => $success,
            'response'   => $success ? "Successfully added new image version." : 'Cache invalidation failed.',
            'identifier' => $media->identifier,
            'version'    => $versionNumber,
            'client'     => $user->name,
        ];
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
