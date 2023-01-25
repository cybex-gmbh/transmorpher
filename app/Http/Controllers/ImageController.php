<?php

namespace App\Http\Controllers;

use App\Enums\MediaType;
use App\Http\Requests\ImageUploadRequest;
use App\Models\User;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

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

        $diskOriginals        = Storage::disk(config('transmorpher.disks.originals'));
        $diskImageDerivatives = Storage::disk(config('transmorpher.disks.imageDerivatives'));

        // Hash of transformation parameters to identify already generated derivatives.
        $derivativeFilename = hash('sha256', $transformations . $currentVersionNumber);

        // Path for (existing) derivative.
        $derivativePath = sprintf('%s/%s/%s', $user->name, $identifier, $derivativeFilename);

        // Check if derivative already exists and return if so.
        if ($diskImageDerivatives->exists($derivativePath)) {
            return response($diskImageDerivatives->get($derivativePath), 200, ['Content-Type' => $diskImageDerivatives->mimeType($derivativePath)]);
        }

        $transformationsArray = $this->getTransformations($transformations);

        $originalFilePath = sprintf('%s/%s/%s', $user->name, $media->identifier, $currentVersion->filename);
        $image            = Image::make($diskOriginals->get($originalFilePath));

        $derivative = $image->resize($transformationsArray['w'] ?? null, $transformationsArray['h'] ?? null, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        })->encode($transformationsArray['f'], $transformationsArray['q'] ?? null);

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
        $disk->putFileAs($path, $imageFile, $filename);

        // Create new version in database.
        $media->Versions()->create(['number' => $versionNumber, 'filename' => $filename]);

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
}
