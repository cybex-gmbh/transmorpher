<?php

namespace App\Http\Controllers;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Helpers\ChunkedUpload;
use App\Http\Requests\UploadRequest;
use App\Models\UploadSlot;
use File;
use FilePathHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Pion\Laravel\ChunkUpload\Exceptions\UploadFailedException;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Transcode;

class VideoController extends Controller
{
    /**
     * Handles incoming image upload requests.
     *
     * @param UploadRequest $request
     * @param UploadSlot    $uploadSlot
     *
     * @return JsonResponse
     * @throws UploadFailedException
     * @throws UploadMissingFileException
     * @throws ValidationException
     */
    public function receiveFile(UploadRequest $request, UploadSlot $uploadSlot): JsonResponse
    {
        if (($response = ChunkedUpload::receive($request)) instanceof JsonResponse) {
            return $response;
        }

        return $this->saveFile($response, $uploadSlot);
    }

    /**
     * @param UploadedFile $videoFile
     * @param UploadSlot   $uploadSlot
     *
     * @return JsonResponse
     * @throws ValidationException
     */
    public function saveFile(UploadedFile $videoFile, UploadSlot $uploadSlot): JsonResponse
    {
        $user = $uploadSlot->User;
        $identifier = $uploadSlot->identifier;

        $media = $user->Media()->whereIdentifier($identifier)->firstOrNew(['identifier' => $identifier, 'type' => MediaType::VIDEO]);
        $media->validateUploadFile($videoFile, 'mimetypes:video/x-msvideo,video/mpeg,video/ogg,video/webm,video/mp4', $uploadSlot);
        $media->save();

        $versionNumber = $media->Versions()->max('number') + 1;
        $fileName = FilePathHelper::createOriginalFileName($versionNumber, $videoFile->getClientOriginalName());
        $basePath = FilePathHelper::toBaseDirectory($user, $identifier);
        $originalsDisk = MediaStorage::ORIGINALS->getDisk();

        if (!$filePath = $originalsDisk->putFileAs($basePath, $videoFile, $fileName)) {
            $success = false;
            $response = 'Could not write video to disk.';
            $versionNumber -= 1;
        } else {
            $version = $media->Versions()->create(['number' => $versionNumber, 'filename' => $fileName]);
            $success = Transcode::createJob($filePath, $media, $version, $uploadSlot);

            if (!$success) {
                $originalsDisk->delete($filePath);
                $version->delete();

                $response ??= 'There was an error when trying to dispatch the transcoding job.';
                $versionNumber -= 1;
            }
        }

        // Delete chunk file.
        File::delete($videoFile);

        return response()->json([
            'success' => $success ?? true,
            'response' => $response ?? "Successfully uploaded video, transcoding job has been dispatched.",
            'identifier' => $media->identifier,
            'version' => $versionNumber,
            'client' => $user->name,
            'upload_token' => $uploadSlot->token,
        ], 201);
    }
}
