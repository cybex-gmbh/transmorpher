<?php

namespace App\Http\Controllers;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Http\Requests\VideoUploadRequest;
use App\Models\User;
use FilePathHelper;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Transcode;

class VideoController extends Controller
{
    /**
     * Handles incoming image upload requests.
     *
     * @param VideoUploadRequest $request
     *
     * @return JsonResponse
     */
    public function put(VideoUploadRequest $request): JsonResponse
    {
        $user        = $request->user();
        $videoFile   = $request->file('video');
        $identifier  = $request->get('identifier');
        $idToken     = $request->get('id_token');
        $callbackUrl = $request->get('callback_url');
        $disk        = MediaStorage::ORIGINALS->getDisk();

        // Save video to disk, create database entry and dispatch transcoding job.
        $response = $this->processVideo($videoFile, $user, $identifier, $idToken, $callbackUrl, $disk);

        return response()->json($response, 201);
    }

    /**
     * Saves an uploaded video to the specified disk.
     * Creates a new version for the identifier in the database.
     * Initiates a transcoding job.
     *
     * @param UploadedFile $videoFile
     * @param User         $user
     * @param string       $identifier
     * @param string       $idToken
     * @param string       $callbackUrl
     * @param Filesystem   $disk
     *
     * @return array
     */
    protected function processVideo(UploadedFile $videoFile, User $user, string $identifier, string $idToken, string $callbackUrl, Filesystem $disk): array
    {
        $media         = $user->Media()->whereIdentifier($identifier)->firstOrCreate(['identifier' => $identifier, 'type' => MediaType::VIDEO]);
        $versionNumber = $media->Versions()->max('number') + 1;
        $basePath      = FilePathHelper::getOriginalsBasePath($user, $identifier);
        $fileName      = FilePathHelper::createOriginalFileName($versionNumber, $videoFile->getClientOriginalName());

        $filePath = $disk->putFileAs($basePath, $videoFile, $fileName);
        $version  = $media->Versions()->create(['number' => $versionNumber, 'filename' => $fileName]);

        $success = Transcode::createJob($filePath, $media, $version, $callbackUrl, $idToken, $disk);

        if (!$success) {
            $disk->delete($filePath);
            $version->delete();

            $versionNumber -= 1;
        }

        return [
            'success'    => $success,
            'response'   => $success ? "Successfully uploaded video, transcoding has started." : 'There was an error when uploading the video.',
            'identifier' => $media->identifier,
            'version'    => $versionNumber,
            'client'     => $user->name,
        ];
    }
}
