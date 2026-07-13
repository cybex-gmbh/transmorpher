<?php

namespace App\Classes\Upload;

use App\Enums\MediaStorage;
use App\Http\Requests\V2\CompleteUploadRequest;
use App\Interfaces\UploadContract;
use App\Models\UploadSlot;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DefaultUpload implements UploadContract
{
    public static function createTempFilename(UploadSlot $uploadSlot): string
    {
        return sprintf('%s.finished.part', $uploadSlot->originalFilename);
    }

    /**
     * Local uploader has no external prerequisites.
     *
     * @return void
     */
    public function ensurePrerequisitesMet(): void
    {
    }

    /**
     * No-op for local uploads.
     *
     * @param UploadSlot $uploadSlot
     * @return void
     */
    public function initiate(UploadSlot $uploadSlot): void
    {
        // No initiation needed.
    }

    /**
     * Returns the v2 chunk upload endpoint URL.
     *
     * @param UploadSlot $uploadSlot
     * @param int $chunkNumber
     * @return string
     */
    public function getChunkUploadUrl(UploadSlot $uploadSlot, int $chunkNumber): string
    {
        return route('v2.upload', $uploadSlot->token);
    }

    /**
     * Completes local upload by validating the file already stored at its reserved destination.
     *
     * @param CompleteUploadRequest $request
     * @param UploadSlot $uploadSlot
     * @return void
     */
    public function complete(CompleteUploadRequest $request, UploadSlot $uploadSlot): void
    {
        $chunkDisk = Storage::disk(config('chunk-upload.storage.disk'));
        $temporaryPath = implode(DIRECTORY_SEPARATOR, [config('chunk-upload.storage.chunks'), static::createTempFilename($uploadSlot)]);

        if (!$chunkDisk->exists($temporaryPath)) {
            throw new RuntimeException('No temporary file found for this upload slot. Ensure the chunk upload completed.');
        }

        $mimeType = mime_content_type($chunkDisk->path($temporaryPath));

        $typeHandler = $uploadSlot->media_type->handler();
        if (!$typeHandler->isMimeTypeValid($mimeType)) {
            throw ValidationException::withMessages([
                'file' => [
                    trans('validation.mimetypes', ['attribute' => 'file', 'values' => $typeHandler->getValidationRules()])
                ]
            ]);
        }

        $stream = $chunkDisk->readStream($temporaryPath);

        $writeSuccess = MediaStorage::ORIGINALS->getDisk()->writeStream($uploadSlot->originalFilePath, $stream);

        fclose($stream);
        $chunkDisk->delete($temporaryPath);

        if (!$writeSuccess) {
            throw new RuntimeException('Could not write assembled upload to final storage.');
        }
    }

    /**
     * Cleans up any already-stored local upload for the given upload slot.
     *
     * @param UploadSlot $uploadSlot
     * @return void
     */
    public function abort(UploadSlot $uploadSlot): void
    {
        Storage::disk(config('chunk-upload.storage.disk'))
            ->delete(
                implode(DIRECTORY_SEPARATOR, [
                    config('chunk-upload.storage.chunks'),
                    static::createTempFilename($uploadSlot)
                ])
            );
    }

    /**
     * Local uploads do not use an upload ID.
     *
     * @param UploadSlot $uploadSlot
     * @return string|null
     */
    public function getUploadId(UploadSlot $uploadSlot): ?string
    {
        return null;
    }

    /**
     * Local uploads do not need an upload ID.
     *
     * @return false
     */
    public function needsUploadId(): bool
    {
        return false;
    }

    /**
     * No completion validation rules needed for local uploads.
     *
     * @return array
     */
    public function getCompletionValidationRules(): array
    {
        return [];
    }
}


