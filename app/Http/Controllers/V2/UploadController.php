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
use App\Models\Media;
use App\Models\UploadSlot;
use App\Models\User;
use App\Models\Version;
use File;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use League\Flysystem\UnableToWriteFile;
use Log;
use Pion\Laravel\ChunkUpload\Exceptions\UploadFailedException;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;
use Throwable;
use Upload;

class UploadController extends Controller
{
    /**
     * Reserves an upload slot for the given media type and initiates the upload.
     *
     * @param User $user
     * @param UploadSlotRequest $request
     * @param MediaType $mediaType
     *
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

        $responseState ??= ResponseState::UPLOAD_SLOT_CREATED;

        return response()->json([
            'state' => $responseState->getState()->value,
            'message' => $responseState->getMessage(),
            'identifier' => $uploadSlot->identifier,
            'upload_token' => $uploadSlot->token,
        ])->setStatusCode($responseState->getResponseCode());
    }

    /**
     * Used by the {@link DefaultUpload} handler.
     *
     * Receives a file chunk.
     *
     * The assembled file is persisted as a temporary .finished.part file in the chunk storage,
     * because later on we will not have access to the correct FileReceiver instance mapping to the chunks.
     *
     * @param UploadRequest $request
     * @param UploadSlot $uploadSlot
     *
     * @return JsonResponse
     *
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
            throw UnableToWriteFile::atLocation(
                implode(DIRECTORY_SEPARATOR, [config('chunk-upload.storage.chunks'), DefaultUpload::createTempFilename($uploadSlot)]),
                sprintf('Intended disk: %s.', config('chunk-upload.storage.disk'))
            );
        }

        return response()->json([
            'done' => 100,
        ]);

    }

    /**
     * Returns a chunk upload URL for the given chunk number.
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
     * Completes the upload process by saving the file and creating a new version.
     *
     * @param CompleteUploadRequest $request
     * @param UploadSlot $uploadSlot
     *
     * @return JsonResponse
     */
    public function completeUpload(CompleteUploadRequest $request, UploadSlot $uploadSlot): JsonResponse
    {
        // Invalidate this upload slot so no other uploads can be done with this token.
        $uploadSlot->invalidate();

        $responseState = $this->completeFileOperations($request, $uploadSlot);

        $version = null;
        if ($responseState?->getState() !== UploadState::ERROR) {
            $version = $this->createVersion($uploadSlot);
            $media = $version->Media;

            $responseState = $media->type->handler()->handleSavedFile($media->baseDirectory(), $uploadSlot, $version);

            if ($responseState->getState() === UploadState::ERROR) {
                // Deleting the version will delete previously stored files.
                $version->delete();

                if (!$media->Versions()->exists()) {
                    $media->delete();
                }
            }
        }

        return response()->json([
            'state' => $responseState->getState()->value,
            'message' => $responseState->getMessage(),
            'identifier' => $uploadSlot->identifier,
            'version' => $version?->exists ? $version->number : Media::firstWhere('identifier', $uploadSlot->identifier)?->latestVersion?->number ?? 0,
            // Public path is only available for on-demand media, since videos are not available at this path yet.
            'public_path' => $uploadSlot->media_type->isInstantlyAvailable() ? implode(DIRECTORY_SEPARATOR, array_filter([$uploadSlot->media_type->prefix(), $uploadSlot->baseDirectory])) : null,
            'upload_token' => $uploadSlot->token,
            'hash' => $uploadSlot->media_type->isInstantlyAvailable() && $version?->exists ? $version?->hash : null,
        ])->setStatusCode($responseState->getResponseCode());
    }

    /**
     * Invalidates the upload slot and aborts the upload process.
     *
     * @param UploadSlot $uploadSlot
     *
     * @return JsonResponse
     */
    public function abortUpload(UploadSlot $uploadSlot): JsonResponse
    {
        $uploadSlot->invalidate();
        $this->abort($uploadSlot);

        return response()->json([
            'state' => ResponseState::UPLOAD_ABORTED->getState()->value,
            'message' => ResponseState::UPLOAD_ABORTED->getMessage(),
            'identifier' => $uploadSlot->identifier,
        ])->setStatusCode(ResponseState::UPLOAD_ABORTED->getResponseCode());
    }

    protected function abort(UploadSlot $uploadSlot): void
    {
        try {
            Upload::abort($uploadSlot);
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }

    protected function completeFileOperations(CompleteUploadRequest $request, UploadSlot $uploadSlot): ?ResponseState
    {
        try {
            Upload::complete($request, $uploadSlot);
        } catch (Throwable $throwable) {
            $this->abort($uploadSlot);

            // If the validation failed, we want to show it to the user directly.
            if ($throwable instanceof ValidationException) {
                throw $throwable;
            }

            report($throwable);
            $responseState = ResponseState::WRITE_FAILED;
        }

        return $responseState ?? null;
    }

    protected function createVersion(UploadSlot $uploadSlot): Version
    {
        $type = $uploadSlot->media_type;

        $media = $uploadSlot->User->Media()->firstOrNew(['identifier' => $uploadSlot->identifier, 'type' => $type]);
        $media->save();

        $versionNumber = $media->latestVersion?->number + 1;
        $version = $media->Versions()->create(['number' => $versionNumber]);
        $version->update(['filename' => $uploadSlot->originalFilename]);

        Log::info(sprintf('Version %s for Media %s created successfully.', $media->identifier, $version->number));

        return $version;
    }
}





