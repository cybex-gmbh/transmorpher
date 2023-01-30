<?php

namespace App\Http\Controllers;

use App\Enums\MediaType;
use App\Http\Requests\ImageUploadRequest;
use App\Models\User;
use Aws\CloudFront\CloudFrontClient;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelImageOptimizer\Facades\ImageOptimizer;

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
        $media                = $user->Media()->whereIdentifier($identifier)->firstOrFail();
        $currentVersionNumber = $media->Versions()->max('number');
        $currentVersion       = $media->Versions()->whereNumber($currentVersionNumber)->first();

        $diskImageDerivatives = Storage::disk(config('transmorpher.disks.imageDerivatives'));

        // Hash of transformation parameters and current version number to identify already generated derivatives.
        $derivativeFilename = hash('sha256', $transformations . $currentVersionNumber);

        // Path for (existing) derivative.
        $derivativePath = sprintf('%s/%s/%s', $user->name, $identifier, $derivativeFilename);

        // Check if derivative already exists and return if so.
        if ($diskImageDerivatives->exists($derivativePath)) {
            return response($diskImageDerivatives->get($derivativePath), 200, ['Content-Type' => $diskImageDerivatives->mimeType($derivativePath)]);
        }

        $transformationsArray = $this->getTransformations($transformations);

        $originalFilePath = sprintf('%s/%s/%s', $user->name, $media->identifier, $currentVersion->filename);

        // Apply transformations to image.
        $derivative = config('transmorpher.transmorpher')::transmorph($originalFilePath, $transformationsArray);
        $derivative = $this->optimizeDerivative($derivative);

        $diskImageDerivatives->put($derivativePath, $derivative);

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
    private function saveImage(UploadedFile $imageFile, User $user, string $identifier, FilesystemAdapter $disk): array
    {
        $media         = $user->Media()->whereIdentifier($identifier)->firstOrCreate(['identifier' => $identifier, 'type' => MediaType::IMAGE]);
        $versionNumber = $media->Versions()->max('number') + 1;

        // Path structure: <username>/<identifier>
        $path = sprintf('%s/%s', $user->name, $media->identifier);

        // Filename structure: <versionNr>-<filename>
        $filename = sprintf('%d-%s', $versionNumber, $imageFile->getClientOriginalName());

        // Save image to disk.
        $filePath = $disk->putFileAs($path, $imageFile, $filename);

        // Create new version in database.
        $version = $media->Versions()->create(['number' => $versionNumber, 'filename' => $filename]);

        // Invalidate cache and delete entry if failed.
        if (config('transmorpher.aws.cloudfront_distribution_id')) {
            try {
                $this->invalidateCdnCache($path);
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
    private function getTransformations(string $transformations): array|null
    {
        if (!$transformations) {
            return null;
        }

        $transformationsArray = null;

        $parameters = explode('+', $transformations);

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
    public function optimizeDerivative($derivative): string|false
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

    private function invalidateCdnCache(string $path): void
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

    private function getCallerReference(): string
    {
        return uniqid();
    }
}
