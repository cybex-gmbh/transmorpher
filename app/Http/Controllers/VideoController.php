<?php

namespace App\Http\Controllers;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Helpers\ChunkedUpload;
use App\Http\Requests\UploadRequest;
use App\Models\UploadToken;
use File;
use FilePathHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Pion\Laravel\ChunkUpload\Exceptions\UploadFailedException;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Transcode;
use Validator;

class VideoController extends Controller
{
    /**
     * Handles incoming image upload requests.
     *
     * @param UploadRequest $request
     *
     * @return JsonResponse
     * @throws UploadMissingFileException
     * @throws ValidationException
     * @throws UploadFailedException
     */
    public function receiveFile(UploadRequest $request): JsonResponse
    {
        // Confirm upload token exists and is still valid.
        $uploadToken = UploadToken::whereToken($request->input('upload_token'))->first();
        if (($response = ChunkedUpload::checkToken($uploadToken)) !== true) {
            return $response;
        }

        if (($response = ChunkedUpload::receive($request, MediaType::VIDEO)) instanceof JsonResponse) {
            return $response;
        }

        return $this->saveFile($response, $uploadToken);
    }

    /**
     * @param UploadedFile $videoFile
     * @param UploadToken  $uploadToken
     *
     * @return JsonResponse
     * @throws ValidationException
     */
    public function saveFile(UploadedFile $videoFile, UploadToken $uploadToken): JsonResponse
    {
        /** Mimetypes to mimes:
         *      video/x-msvideo    => avi
         *      video/mpeg      => mpeg mpg mpe m1v m2v
         *      video/ogg        => ogv
         *      video/webm        => webm
         *      video/mp4        => mp4 mp4v mpg4
         */
        $validator = Validator::make(['video' => $videoFile], ['video' => [
            'required',
            'mimetypes:video/x-msvideo,video/mpeg,video/ogg,video/webm,video/mp4',
        ]]);

        $failed = $validator->fails();

        $validator->after(function () use ($videoFile, $failed, $uploadToken) {
            if ($failed) {
                File::delete($videoFile);
                $uploadToken->delete();
            }
        });

        $validator->validate();

        $user = $uploadToken->User;
        $identifier = $uploadToken->identifier;
        $media = $user->Media()->whereIdentifier($identifier)->firstOrCreate(['identifier' => $identifier, 'type' => MediaType::VIDEO]);
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
            $success = Transcode::createJob($filePath, $media, $version, $uploadToken->callback_url, $uploadToken->id_token);

            if (!$success) {
                $originalsDisk->delete($filePath);
                $version->delete();

                $response ??= 'There was an error when trying to dispatch the transcoding job.';
                $versionNumber -= 1;
            }
        }

        // Delete chunk file and token.
        File::delete($videoFile);
        $uploadToken->delete();

        return response()->json([
            'success' => $success ?? true,
            'response' => $response ?? "Successfully uploaded video, transcoding job has been dispatched.",
            'identifier' => $media->identifier,
            'version' => $versionNumber,
            'client' => $user->name,
        ], 201);
    }
}
