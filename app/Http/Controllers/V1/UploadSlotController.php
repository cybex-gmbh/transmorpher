<?php

namespace App\Http\Controllers\V1;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Enums\ResponseState;
use App\Enums\UploadState;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\UploadRequest;
use App\Http\Requests\V1\UploadSlotRequest;
use App\Models\UploadSlot;
use App\Models\User;
use File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Pion\Laravel\ChunkUpload\Exceptions\UploadFailedException;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;

class UploadSlotController extends Controller
{
    /**
     * @param UploadRequest $request
     * @param UploadSlot $uploadSlot
     * @return JsonResponse|UploadedFile
     * @throws UploadFailedException
     * @throws UploadMissingFileException
     */
    public function receiveFile(UploadRequest $request, UploadSlot $uploadSlot): JsonResponse|UploadedFile
    {
        $receiver = new FileReceiver($request->file('file'), $request, HandlerFactory::classFromRequest($request));

        // Check if the chunk is successfully uploaded.
        if ($receiver->isUploaded() === false) {
            throw new UploadMissingFileException();
        }

        $save = $receiver->receive();

        // Check if the full upload has finished and if so, save the file.
        if ($save->isFinished()) {
            return $this->saveFile($save->getFile(), $uploadSlot, $uploadSlot->media_type);
        }

        // Full file is not yet uploaded, send the current progress.
        return response()->json([
            "done" => $save->handler()->getPercentageDone(),
        ]);
    }

    /**
     * Handle the incoming request.
     *
     * @param UploadSlotRequest $request
     *
     * @return JsonResponse
     */
    public function reserveImageUploadSlot(UploadSlotRequest $request): JsonResponse
    {
        return $this->reserveUploadSlot($request, MediaType::IMAGE);
    }

    /**
     * Handle the incoming request.
     *
     * @param UploadSlotRequest $request
     *
     * @return JsonResponse
     */
    public function reserveVideoUploadSlot(UploadSlotRequest $request): JsonResponse
    {
        return $this->reserveUploadSlot($request, MediaType::VIDEO);
    }

    protected function reserveUploadSlot(UploadSlotRequest $request, MediaType $mediaType): JsonResponse
    {
        return $this->updateOrCreateUploadSlot($request->user(), $request->merge(['media_type' => $mediaType->value])->all());
    }

    /**
     * @param UploadedFile $uploadedFile
     * @param UploadSlot $uploadSlot
     * @param MediaType $type
     *
     * @return JsonResponse
     */
    protected function saveFile(UploadedFile $uploadedFile, UploadSlot $uploadSlot, MediaType $type): JsonResponse
    {
        // Invalidate this upload slot so no other uploads can be done with this token.
        $uploadSlot->invalidate();

        $media = $uploadSlot->User->Media()->firstOrNew(['identifier' => $uploadSlot->identifier, 'type' => $type]);
        $media->validateUploadFile($uploadedFile, $type->handler()->getValidationRules());
        $media->save();

        $versionNumber = $media->latestVersion?->number + 1;
        $version = $media->Versions()->create(['number' => $versionNumber]);
        $basePath = $media->baseDirectory();

        $version->update(['filename' => $version->createOriginalFileName($uploadedFile->getClientOriginalName())]);

        if (MediaStorage::ORIGINALS->getDisk()->putFileAs($basePath, $uploadedFile, $version->filename)) {
            \Log::info(sprintf('File for media %s and version %s saved successfully.', $media->identifier, $version->number));
            $responseState = $type->handler()->handleSavedFile($basePath, $uploadSlot, $version);
        } else {
            \Log::error(sprintf('Could not write file for media %s and version %s.', $media->identifier, $version->number));
            $responseState = ResponseState::WRITE_FAILED;
        }

        if ($responseState->getState() === UploadState::ERROR) {
            $versionNumber -= 1;
            $version->delete();
        }

        // Delete local file.
        File::delete($uploadedFile);

        return response()->json([
            'state' => $responseState->getState()->value,
            'message' => $responseState->getMessage(),
            'identifier' => $media->identifier,
            'version' => $versionNumber,
            // Base path is only passed for images since the video is not available at this path yet.
            'public_path' => $type->isInstantlyAvailable() ? implode(DIRECTORY_SEPARATOR, array_filter([$type->prefix(), $basePath])) : null,
            'upload_token' => $uploadSlot->token,
            'hash' => $type->isInstantlyAvailable() ? $version?->hash : null,
        ], 201);
    }

    protected function updateOrCreateUploadSlot(User $user, array $requestData): JsonResponse
    {
        // Token and valid_until will be set in the 'saving' event.
        $uploadSlot = $user->UploadSlots()->withoutGlobalScopes()->updateOrCreate(['identifier' => $requestData['identifier']], $requestData);

        return response()->json([
            'state' => ResponseState::UPLOAD_SLOT_CREATED->getState()->value,
            'message' => ResponseState::UPLOAD_SLOT_CREATED->getMessage(),
            'identifier' => $requestData['identifier'],
            'upload_token' => $uploadSlot->token
        ]);
    }
}
