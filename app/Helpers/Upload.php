<?php

namespace App\Helpers;

use App\Enums\ImageFormat;
use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Http\Requests\UploadRequest;
use App\Models\UploadSlot;
use CdnHelper;
use File;
use FilePathHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Pion\Laravel\ChunkUpload\Exceptions\UploadFailedException;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;
use Throwable;
use Transcode;

class Upload
{
    /**
     * @param UploadRequest $request
     *
     * @return JsonResponse|UploadedFile
     * @throws UploadFailedException
     * @throws UploadMissingFileException
     */
    public static function receive(UploadRequest $request): JsonResponse|UploadedFile
    {
        $receiver = new FileReceiver($request->file('file'), $request, HandlerFactory::classFromRequest($request));

        // Check if the chunk is successfully uploaded.
        if ($receiver->isUploaded() === false) {
            throw new UploadMissingFileException();
        }

        $save = $receiver->receive();

        // Check if the full upload has finished and if so, return the file.
        if ($save->isFinished()) {
            return $save->getFile();
        }

        // Full file is not yet uploaded, send the current progress.
        return response()->json([
            "done" => $save->handler()->getPercentageDone(),
        ]);
    }

    public static function saveFile(UploadedFile $uploadedFile, UploadSlot $uploadSlot, MediaType $type): JsonResponse
    {
        $isImage = $type === MediaType::IMAGE;
        $user = $uploadSlot->User;
        $identifier = $uploadSlot->identifier;

        $media = $user->Media()->firstOrNew(['identifier' => $identifier, 'type' => $type]);
        $mimeTypes = $isImage ?
            sprintf('mimes:%s', implode(',', ImageFormat::getFormats()))
            : 'mimetypes:video/x-msvideo,video/mpeg,video/ogg,video/webm,video/mp4';
        $media->validateUploadFile($uploadedFile, $mimeTypes, $uploadSlot);
        $media->save();

        $versionNumber = $media->Versions()->max('number') + 1;
        $basePath = FilePathHelper::toBaseDirectory($user, $identifier);
        $fileName = FilePathHelper::createOriginalFileName($versionNumber, $uploadedFile->getClientOriginalName());
        $originalsDisk = MediaStorage::ORIGINALS->getDisk();

        $success = true;
        if ($filePath = $originalsDisk->putFileAs($basePath, $uploadedFile, $fileName)) {
            $version = $media->Versions()->create(['number' => $versionNumber, 'filename' => $fileName]);

            if ($isImage) {
                // Invalidate cache and delete entry if failed.
                if (CdnHelper::isConfigured()) {
                    try {
                        CdnHelper::invalidateImage($basePath);
                    } catch (Throwable) {
                        $success = false;
                        $response = 'Cache invalidation failed.';
                    }
                }

                // Only delete for image, since the UploadSlot will be needed inside the transcoding job.
                $uploadSlot->delete();
            } else {
                $success = Transcode::createJob($filePath, $media, $version, $uploadSlot);

                if (!$success) {
                    $response = 'There was an error when trying to dispatch the transcoding job.';
                }
            }
        } else {
            $success = false;
            $response = 'Could not write media to disk.';
        }

        if (!$success) {
            $versionNumber -= 1;
            $originalsDisk->delete($filePath);
            $version?->delete();
        }

        // Delete chunk file and token.
        File::delete($uploadedFile);

        // Todo: to ensure that failed uploads don't pollute the image derivative cache, we would need a ready flag that is set to true when CDN is invalidated.

        return response()->json([
            'success' => $success ?? true,
            'response' => $response ?? $isImage ? 'Successfully added new image version.' : 'Successfully uploaded video, transcoding job has been dispatched.',
            'identifier' => $media->identifier,
            'version' => $versionNumber,
            'client' => $user->name,
            'public_path' =>$isImage ? $basePath : null,
            'upload_token' => $uploadSlot->token
        ], 201);
    }
}
