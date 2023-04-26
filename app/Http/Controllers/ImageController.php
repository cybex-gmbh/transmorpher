<?php

namespace App\Http\Controllers;

use App\Enums\ImageFormat;
use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Enums\Transformation;
use App\Helpers\ChunkedUpload;
use App\Http\Requests\UploadRequest;
use App\Models\UploadToken;
use App\Models\User;
use CdnHelper;
use File;
use FilePathHelper;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Pion\Laravel\ChunkUpload\Exceptions\UploadFailedException;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Spatie\ImageOptimizer\OptimizerChain;
use Spatie\ImageOptimizer\Optimizers\Optipng;
use Spatie\ImageOptimizer\Optimizers\Pngquant;
use Spatie\LaravelImageOptimizer\Facades\ImageOptimizer;
use Throwable;
use Transform;
use Validator;

class ImageController extends Controller
{
    /**
     * Handles incoming image upload requests.
     *
     * @param UploadRequest $request
     *
     * @return JsonResponse
     * @throws UploadFailedException
     * @throws UploadMissingFileException
     * @throws ValidationException
     */
    public function receiveFile(UploadRequest $request): JsonResponse
    {
        // Confirm upload token exists and is still valid.
        $uploadToken = UploadToken::whereToken($request->input('upload_token'))->first();
        if (($response = ChunkedUpload::checkToken($uploadToken)) !== true) {
            return $response;
        }

        if (($response = ChunkedUpload::receive($request, MediaType::IMAGE)) instanceof JsonResponse) {
            return $response;
        }

        return $this->saveFile($response, $uploadToken);
    }

    /**
     * @param UploadedFile $imageFile
     * @param UploadToken $uploadToken
     *
     * @return JsonResponse
     * @throws ValidationException
     */
    public function saveFile(UploadedFile $imageFile, UploadToken $uploadToken): JsonResponse
    {
        $validator = Validator::make(['image' => $imageFile], ['image' => [
            'required',
            sprintf('mimes:%s', implode(',', ImageFormat::getFormats())),
        ]]);

        $failed = $validator->fails();

        $validator->after(function () use ($imageFile, $failed, $uploadToken) {
            if ($failed) {
                File::delete($imageFile);
                $uploadToken->delete();
            }
        });

        $validator->validate();

        $user = $uploadToken->User;
        $identifier = $uploadToken->identifier;
        $media = $user->Media()->whereIdentifier($identifier)->firstOrCreate(['identifier' => $identifier, 'type' => MediaType::IMAGE]);
        $versionNumber = $media->Versions()->max('number') + 1;

        $basePath = FilePathHelper::toBaseDirectory($user, $identifier);
        $fileName = FilePathHelper::createOriginalFileName($versionNumber, $imageFile->getClientOriginalName());
        $originalsDisk = MediaStorage::ORIGINALS->getDisk();

        if ($filePath = $originalsDisk->putFileAs($basePath, $imageFile, $fileName)) {
            $version = $media->Versions()->create(['number' => $versionNumber, 'filename' => $fileName]);

            // Invalidate cache and delete entry if failed.
            if (CdnHelper::isConfigured()) {
                try {
                    CdnHelper::invalidateImage($basePath);
                } catch (Throwable) {
                    $originalsDisk->delete($filePath);
                    $version->delete();

                    $success = false;
                    $response = 'Cache invalidation failed.';
                    $versionNumber -= 1;
                }
            }
        } else {
            $success = false;
            $response = 'Could not write image to disk.';
            $versionNumber -= 1;
        }

        // Delete chunk file and token.
        File::delete($imageFile);
        $uploadToken->delete();

        // Todo: to ensure that failed uploads don't pollute the image derivative cache, we would need a ready flag that is set to true when CDN is invalidated.

        return response()->json([
            'success' => $success ?? true,
            'response' => $response ?? 'Successfully added new image version.',
            'identifier' => $media->identifier,
            'version' => $versionNumber,
            'client' => $user->name,
            'public_path' => $basePath,
        ], 201);
    }

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
    protected function optimizeDerivative($derivative, int $quality = null): string|false
    {
        // Temporary file is needed since optimizers only work locally.
        $tempFile = tempnam(sys_get_temp_dir(), 'transmorpher');
        file_put_contents($tempFile, $derivative);

        if ($quality && mime_content_type($tempFile) === 'image/png') {
            // Add pngquant quality option for pngs
            $pngquant = config(sprintf('image-optimizer.optimizers.%s', Pngquant::class));
            $pngquant[] = sprintf(' --quality %1$s-%1$s', $quality);
            $optipng = config(sprintf('image-optimizer.optimizers.%s', Optipng::class));

            (new OptimizerChain())->addOptimizer(new Pngquant($pngquant))->addOptimizer(new Optipng($optipng))->optimize($tempFile);
        } else {
            // Optimizes the image based on optimizers configured in 'config/image-optimizer.php'.
            ImageOptimizer::optimize($tempFile);
        };

        $derivative = file_get_contents($tempFile);
        unlink($tempFile);

        return $derivative;
    }
}
