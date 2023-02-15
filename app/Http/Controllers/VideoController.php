<?php

namespace App\Http\Controllers;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Http\Requests\VideoUploadRequest;
use FilePathHelper;
use Illuminate\Http\JsonResponse;
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
        $user          = $request->user();
        $videoFile     = $request->file('video');
        $identifier    = $request->get('identifier');
        $media         = $user->Media()->whereIdentifier($identifier)->firstOrCreate(['identifier' => $identifier, 'type' => MediaType::VIDEO]);
        $versionNumber = $media->Versions()->max('number') + 1;

        $fileName      = FilePathHelper::createOriginalFileName($versionNumber, $videoFile->getClientOriginalName());
        $basePath      = FilePathHelper::toBaseDirectory($user, $identifier);
        $originalsDisk = MediaStorage::ORIGINALS->getDisk();



        if (!$filePath = $originalsDisk->putFileAs($basePath, $videoFile, $fileName)) {
            $success       = false;
            $response      = 'Could not write video to disk.';
            $versionNumber -= 1;
        } else {
            $version  = $media->Versions()->create(['number' => $versionNumber, 'filename' => $fileName]);
            $success  = Transcode::createJob($filePath, $media, $version, $request->get('callback_url'), $request->get('id_token'));

            if (!$success) {
                $originalsDisk->delete($filePath);
                $version->delete();

                $response      ??= 'There was an error when trying to dispatch the transcoding job.';
                $versionNumber -= 1;
            }
        }

        return response()->json([
            'success'    => $success ?? true,
            'response'   => $response ?? "Successfully uploaded video, transcoding job has been dispatched.",
            'identifier' => $media->identifier,
            'version'    => $versionNumber,
            'client'     => $user->name,
        ], 201);
    }
}
