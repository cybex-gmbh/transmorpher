<?php

namespace App\Http\Controllers;

use App;
use App\Enums\MediaType;
use App\Http\Requests\ImageUploadRequest;
use App\Models\User;
use Aws\CloudFront\CloudFrontClient;
use Exception;
use FilePathHelper;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelImageOptimizer\Facades\ImageOptimizer;
use Storage;

class ImageController extends Controller
{
    /**
     * Handles incoming image upload requests.
     *
     * @param ImageUploadRequest $request
     *
     * @return JsonResponse
     */
    public function store(ImageUploadRequest $request): JsonResponse
    {
        $user       = $request->user();
        $imageFile  = $request->file('image');
        $identifier = $request->get('identifier');
        $disk       = Storage::disk(config('transmorpher.disks.originals'));

        // Save image to disk and create database entry.
        $response = $this->saveImage($imageFile, $user, $identifier, $disk);

        return response()->json($response, 201);
    }

    /**
     * @param User   $user
     * @param string $identifier
     * @param string $transformations
     *
     * @return Application|ResponseFactory|Response
     */
    public function get(User $user, string $identifier, string $transformations = ''): Response|Application|ResponseFactory
    {
        $diskImageDerivatives = Storage::disk(config('transmorpher.disks.imageDerivatives'));
        $transformationsArray = $this->getTransformations($transformations);
        $derivativePath       = FilePathHelper::getImageDerivativePath($user, $transformations, $identifier, $transformationsArray);

        // Check if derivative already exists and return if so.
        if (!config('transmorpher.dev_mode') && config('transmorpher.store_derivatives') && $diskImageDerivatives->exists($derivativePath)) {
            return response($diskImageDerivatives->get($derivativePath), 200, ['Content-Type' => $diskImageDerivatives->mimeType($derivativePath)]);
        }

        $originalFilePath = FilePathHelper::getImageOriginalPath($user, $identifier);

        // Apply transformations to image.
        $derivative = config('transmorpher.transmorpher')::transmorph($originalFilePath, $transformationsArray);
        $derivative = $this->optimizeDerivative($derivative);

        if (config('transmorpher.store_derivatives')) {
            $diskImageDerivatives->put($derivativePath, $derivative);
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
    protected function saveImage(UploadedFile $imageFile, User $user, string $identifier, FilesystemAdapter $disk): array
    {
        $media         = $user->Media()->whereIdentifier($identifier)->firstOrCreate(['identifier' => $identifier, 'type' => MediaType::IMAGE]);
        $versionNumber = $media->Versions()->max('number') + 1;
        $basePath      = FilePathHelper::getOriginalsBasePath($user, $identifier);
        $fileName      = FilePathHelper::createOriginalFileName($versionNumber, $imageFile->getClientOriginalName());

        $filePath = $disk->putFileAs($basePath, $imageFile, $fileName);
        $version  = $media->Versions()->create(['number' => $versionNumber, 'filename' => $fileName]);

        // Invalidate cache and delete entry if failed.
        if (config('transmorpher.aws.cloudfront_distribution_id')) {
            try {
                $this->invalidateCdnCache($basePath);
            } catch (Exception) {
                $disk->delete($filePath);
                $version->delete();

                return [
                    'success' => false,
                    'response' => 'Cache invalidation failed.',
                    'identifier' => $media->identifier,
                    'client' => $user->name,
                ];
            }
        }

        return [
            'success'    => true,
            'response'   => "Successfully added new image version.",
            'identifier' => $media->identifier,
            'version'    => $versionNumber,
            'client'     => $user->name,
        ];
    }

    /**
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

    /**
     * @param string $path
     *
     * @return void
     */
    protected function invalidateCdnCache(string $path): void
    {
        $invalidationPath = sprintf('%s/%s/*', Storage::disk(config('transmorpher.disks.imageDerivatives'))->path(''), $path);

        $cloudFrontClient = new CloudFrontClient([
            'version'     => 'latest',
            'region'      => config('transmorpher.aws.region'),
            'credentials' => [
                'key'    => config('transmorpher.aws.key'),
                'secret' => config('transmorpher.aws.secret'),
            ],
        ]);

        $cloudFrontClient->createInvalidation([
            'DistributionId'    => config('transmorpher.aws.cloudfront_distribution_id'),
            'InvalidationBatch' => [
                'CallerReference' => $this->getCallerReference(),
                'Paths'           => [
                    'Items'    => [$invalidationPath],
                    'Quantity' => 1,
                ],
            ],
        ]);
    }

    /**
     * @return string
     */
    protected function getCallerReference(): string
    {
        return uniqid();
    }
}
