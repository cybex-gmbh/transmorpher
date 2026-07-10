<?php

namespace App\Http\Controllers\V2;

use App\Enums\MediaType;
use App\Enums\ResponseState;
use App\Enums\UploadState;
use App\Facades\UploaderFacade as Uploader;
use App\Http\Controllers\Controller;
use App\Http\Requests\V2\CompleteUploadRequest;
use App\Http\Requests\V2\UploadRequest;
use App\Http\Requests\V2\UploadSlotRequest;
use App\Models\UploadSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Pion\Laravel\ChunkUpload\Exceptions\UploadFailedException;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;

class UploadSlotController extends Controller
{
    /**
     * Reserves an upload slot for the given media type.
     * Calls initiateUpload on the configured uploader.
     *
     * @param UploadSlotRequest $request
     * @param MediaType $mediaType
     * @return JsonResponse
     */
    public function reserveUploadSlot(UploadSlotRequest $request, MediaType $mediaType): JsonResponse
    {
        $user = $request->user();
        $requestData = $request->merge(['media_type' => $mediaType->value])->all();

        $uploadSlot = $user->UploadSlots()->withoutGlobalScopes()->updateOrCreate(
            ['identifier' => $requestData['identifier']],
            $requestData
        );

        // TODO Create migration for UploadSlot and add filename, which will consist of uploadToken + passed filename.
        // Store filename in cache for later use during file saving.
        Cache::put(
            sprintf('filename_%s', $uploadSlot->token),
            $request->input('filename'),
            now()->addHours(24)
        );

        try {
            Uploader::initiateUpload($uploadSlot);
        } catch (\Throwable $throwable) {
            report($throwable);
        }

        return response()->json([
            'state' => ResponseState::UPLOAD_SLOT_CREATED->getState()->value,
            'message' => ResponseState::UPLOAD_SLOT_CREATED->getMessage(),
            'identifier' => $uploadSlot->identifier,
            'upload_token' => $uploadSlot->token,
        ]);
    }

    /**
     * Receives a file chunk for a local upload.
     * When pion signals the upload is complete, caches the assembled file path
     * for retrieval by the complete endpoint.
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

        // TODO: Save the file here to the correct location.
        // When all chunks are assembled, cache the file info for the complete endpoint.
        if ($save->isFinished()) {
            $assembledFile = $save->getFile();

            Cache::put(
                sprintf('assembled_file_%s', $uploadSlot->token),
                [
                    'path' => $assembledFile->getRealPath(),
                    'original_name' => $assembledFile->getClientOriginalName(),
                    'mime_type' => $assembledFile->getMimeType(),
                ],
                now()->addHours(24)
            );

            return response()->json([
                'done' => 100,
            ]);
        }

        // Full file is not yet uploaded, send the current progress.
        return response()->json([
            'done' => $save->handler()->getPercentageDone(),
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
            'url' => Uploader::getChunkUploadUrl($uploadSlot, $chunkNumber),
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
        Uploader::abortUpload($uploadSlot);
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

            // Retrieve original filename stored in cache during reserveUploadSlot.
            $cachedFilename = Cache::get(sprintf('filename_%s', $uploadSlot->token));
            $originalFilename = $cachedFilename
                ?? sprintf('%s.bin', $uploadSlot->identifier);
            $version->update(['filename' => $version->createOriginalFileName($originalFilename)]);

            $completionContext = [
                'validation_rules' => $type->handler()->getValidationRules(),
            ];

            if (!Uploader::needsUploadId()) {
                $completionContext['target_key'] = sprintf('%s/%s', $basePath, $version->filename);
            }

            Uploader::completeUpload($uploadSlot, array_merge($completionData, $completionContext));

            $writeSuccess = true;

            if ($writeSuccess) {
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

            return [$media, $version, $versionNumber, $responseState];
        });

        Cache::forget(sprintf('filename_%s', $uploadSlot->token));


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





