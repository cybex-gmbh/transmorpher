<?php

namespace App\Helpers;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Enums\ResponseState;
use App\Http\Requests\UploadRequest;
use App\Models\UploadSlot;
use App\Models\User;
use Carbon\Carbon;
use File;
use FilePathHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Pion\Laravel\ChunkUpload\Exceptions\UploadFailedException;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;

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

    /**
     * @param UploadedFile $uploadedFile
     * @param UploadSlot $uploadSlot
     * @param MediaType $type
     *
     * @return JsonResponse
     */
    public static function saveFile(UploadedFile $uploadedFile, UploadSlot $uploadSlot, MediaType $type): JsonResponse
    {
        $user = $uploadSlot->User;
        $identifier = $uploadSlot->identifier;

        $media = $user->Media()->firstOrNew(['identifier' => $identifier, 'type' => $type]);
        $media->validateUploadFile($uploadedFile, $type->handler()->getValidationRules(), $uploadSlot);
        $media->save();

        $versionNumber = $media->Versions()->max('number') + 1;
        $basePath = FilePathHelper::toBaseDirectory($user, $identifier);
        $fileName = FilePathHelper::createOriginalFileName($versionNumber, $uploadedFile->getClientOriginalName());
        $originalsDisk = MediaStorage::ORIGINALS->getDisk();

        if ($filePath = $originalsDisk->putFileAs($basePath, $uploadedFile, $fileName)) {
            $version = $media->Versions()->create(['number' => $versionNumber, 'filename' => $fileName]);
            $responseState = $type->handler()->handleSavedFile($basePath, $uploadSlot, $filePath, $media, $version);
        } else {
            $responseState = ResponseState::WRITE_FAILED;
        }

        if (!$responseState->success()) {
            $versionNumber -= 1;
            $originalsDisk->delete($filePath);
            $version?->delete();
        }

        // Delete local file.
        File::delete($uploadedFile);

        return response()->json([
            'success' => $responseState->success(),
            'response' => $responseState->value,
            'identifier' => $media->identifier,
            'version' => $versionNumber,
            'client' => $user->name,
            // Base path is only passed for images since the video is not available at this path yet.
            'public_path' => $type === MediaType::IMAGE ? $basePath : null,
            'upload_token' => $uploadSlot->token
        ], 201);
    }

    /**
     * @param User $user
     * @param string $identifier
     * @param string|null $callbackUrl
     * @param string|null $validationRules
     * @return UploadSlot
     */
    public static function createUploadSlot(User $user, string $identifier, string $callbackUrl = null, string $validationRules = null): UploadSlot
    {
        return $user->UploadSlots()->updateOrCreate([
            'identifier' => $identifier,
        ], [
            'token' => uniqid(),
            'identifier' => $identifier,
            'callback_url' => $callbackUrl,
            'validation_rules' => $validationRules,
            'valid_until' => Carbon::now()->addHours(24)
        ]);
    }
}
