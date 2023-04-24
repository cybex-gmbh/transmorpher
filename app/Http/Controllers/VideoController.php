<?php

namespace App\Http\Controllers;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Http\Requests\UploadRequest;
use App\Models\UploadToken;
use Carbon\Carbon;
use File;
use FilePathHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Pion\Laravel\ChunkUpload\Exceptions\UploadFailedException;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;
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
        if (!$uploadToken) {
            return response()->json([
                'success' => false,
                'response' => 'The upload token is not valid.',
            ], 401);
        }

        if (Carbon::now()->isAfter($uploadToken->valid_until)) {
            $uploadToken->delete();

            return response()->json([
                'success' => false,
                'response' => 'The upload token is no longer valid.',
            ], 410);
        }

        // create the file receiver
        $receiver = new FileReceiver($request->file('video'), $request, HandlerFactory::classFromRequest($request));

        // check if the upload is success, throw exception or return response you need
        if ($receiver->isUploaded() === false) {
            throw new UploadMissingFileException();
        }

        // receive the file
        $save = $receiver->receive();

        // check if the upload has finished (in chunk mode it will send smaller files)
        if ($save->isFinished()) {
            // save the file and return any response you need, current example uses `move` function. If you are
            // not using move, you need to manually delete the file by unlink($save->getFile()->getPathname())
            return $this->saveFile($save->getFile(), $uploadToken);
        }

        // we are in chunk mode, lets send the current progress
        $handler = $save->handler();

        return response()->json([
            "done" => $handler->getPercentageDone(),
        ]);
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
