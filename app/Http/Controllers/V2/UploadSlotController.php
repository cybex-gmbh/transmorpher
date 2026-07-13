<?php

namespace App\Http\Controllers\V2;

use App\Classes\Upload\DefaultUpload;
use App\Enums\MediaType;
use App\Enums\ResponseState;
use App\Enums\UploadState;
use App\Http\Controllers\Controller;
use App\Http\Requests\V2\CompleteUploadRequest;
use App\Http\Requests\V2\UploadRequest;
use App\Http\Requests\V2\UploadSlotRequest;
use App\Models\UploadSlot;
use App\Models\User;
use File;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Log;
use Pion\Laravel\ChunkUpload\Exceptions\UploadFailedException;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;
use RuntimeException;
use Throwable;
use Upload;

class UploadSlotController extends Controller
{
    /**
     * Reserves an upload slot for the given media type and initiates the upload.
     *
     * @param User $user
     * @param UploadSlotRequest $request
     * @param MediaType $mediaType
     * @return JsonResponse
     */
    public function reserveUploadSlot(#[CurrentUser] User $user, UploadSlotRequest $request, MediaType $mediaType): JsonResponse
    {
        $requestData = array_merge(
            $request->validated(),
            ['media_type' => $mediaType->value],
        );

        Log::info(sprintf('Reserving upload slot: User %s, Identifier %s, MediaType %s', $user->id, $requestData['identifier'], $mediaType->value));

        $uploadSlot = $user->UploadSlots()
            ->withoutGlobalScopes()
            ->updateOrCreate(
                ['identifier' => $requestData['identifier']],
                $requestData,
            );

        try {
            Upload::initiate($uploadSlot);
        } catch (Throwable $throwable) {
            report($throwable);

            $responseState = ResponseState::UPLOAD_SLOT_CREATION_FAILED;
        }

        return response()->json([
            'state' => ($responseState ?? ResponseState::UPLOAD_SLOT_CREATED)->getState()->value,
            'message' => ($responseState ?? ResponseState::UPLOAD_SLOT_CREATED)->getMessage(),
            'identifier' => $uploadSlot->identifier,
            'upload_token' => $uploadSlot->token,
        ]);
    }

    /**
     * Used by the {@link DefaultUpload} handler.
     *
     * Receives a file chunk.
     *
     * The assembled file is persisted as a temporary .finished.part file in the chunk storage,
     * because later on we will not have access to the correct FileReceiver instance mapping to the chunks.
     *
     *
     * @param UploadRequest $request
     * @param UploadSlot $uploadSlot
     * @return JsonResponse
     * @throws UploadFailedException
     * @throws UploadMissingFileException
     */
    public function receiveFile(UploadRequest $request, UploadSlot $uploadSlot): JsonResponse
    {
        $receiver = new FileReceiver($request->file('file'), $request, HandlerFactory::classFromRequest($request));

        // Check if the chunk is successfully uploaded.
        if ($receiver->isUploaded() === false) {
            throw new UploadMissingFileException();
        }

        $save = $receiver->receive();

        if (!$save->isFinished()) {
            // Full file is not yet uploaded, send the current progress.
            return response()->json([
                'done' => $save->handler()->getPercentageDone(),
            ]);
        }

        // All chunks have been received.
        $assembledFile = $save->getFile();

        $writeSuccess = Storage::disk(config('chunk-upload.storage.disk'))->putFileAs(
            config('chunk-upload.storage.chunks'),
            $assembledFile,
            DefaultUpload::createTempFilename($uploadSlot),
        );

        File::delete($assembledFile->getRealPath());

        if (!$writeSuccess) {
            throw new RuntimeException('Could not write assembled upload to chunk temporary storage.');
        }

        return response()->json([
            'done' => 100,
        ]);

    }

    /**
     * Returns a (signed) chunk upload URL for the given chunk number.
     *
     * @param UploadSlot $uploadSlot
     * @param int $chunkNumber
     *
     * @return JsonResponse
     */
    public function getChunkUploadUrl(UploadSlot $uploadSlot, int $chunkNumber): JsonResponse
    {
        return response()->json([
            'url' => Upload::getChunkUploadUrl($uploadSlot, $chunkNumber),
        ]);
    }

    /**
     * Completes the upload process and saves the file.
     *
     * @param CompleteUploadRequest $request
     * @param UploadSlot $uploadSlot
     * @return JsonResponse
     */
    public function completeUpload(CompleteUploadRequest $request, UploadSlot $uploadSlot): JsonResponse
    {
        return $this->saveFile($uploadSlot, $request->validated());
    }

    /**
     * Aborts the upload process and invalidates the upload slot.
     *
     * @param UploadSlot $uploadSlot
     * @return JsonResponse
     */
    public function abortUpload(UploadSlot $uploadSlot): JsonResponse
    {
        Upload::abort($uploadSlot);
        $uploadSlot->invalidate();

        return response()->json([
            'state' => ResponseState::UPLOAD_ABORTED->getState()->value,
            'message' => ResponseState::UPLOAD_ABORTED->getMessage(),
            'identifier' => $uploadSlot->identifier,
        ]);
    }

    /**
     * Completes the upload and persists the media and version records.
     *
     * @param UploadSlot $uploadSlot
     * @param array $completionData
     * @return JsonResponse
     */
    protected function saveFile(UploadSlot $uploadSlot, array $completionData): JsonResponse
    {
        $type = $uploadSlot->media_type;

        // Invalidate this upload slot so no other uploads can be done with this token.
        $uploadSlot->invalidate();

        [$media, $version, $versionNumber, $responseState] = DB::transaction(function () use ($uploadSlot, $type, $completionData) {
            $media = $uploadSlot->User->Media()->firstOrNew(['identifier' => $uploadSlot->identifier, 'type' => $type]);

            $media->save();

            $versionNumber = $media->latestVersion?->number + 1;
            $version = $media->Versions()->create(['number' => $versionNumber]);
            $basePath = $media->baseDirectory();

            $version->update(['filename' => $uploadSlot->originalFilename]);

            $completionContext = [
                'validation_rules' => $type->handler()->getValidationRules(),
            ];

            Upload::complete($uploadSlot, array_merge($completionData, $completionContext));

            $writeSuccess = true;

            if ($writeSuccess) {
                Log::info(sprintf('File for media %s and version %s saved successfully.', $media->identifier, $version->number));
                $responseState = $type->handler()->handleSavedFile($basePath, $uploadSlot, $version);
            } else {
                Log::error(sprintf('Could not write file for media %s and version %s.', $media->identifier, $version->number));
                $responseState = ResponseState::WRITE_FAILED;
            }

            if ($responseState->getState() === UploadState::ERROR) {
                $versionNumber -= 1;
                $version->delete();
            }

            return [$media, $version, $versionNumber, $responseState];
        });


        $basePath = $media->baseDirectory();

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
}





