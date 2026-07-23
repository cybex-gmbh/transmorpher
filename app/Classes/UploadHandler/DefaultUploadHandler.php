<?php

namespace App\Classes\UploadHandler;

use App\Enums\MediaStorage;
use App\Http\Requests\V2\CompleteUploadRequest;
use App\Interfaces\UploadHandlerInterface;
use App\Models\UploadSlot;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToWriteFile;

class DefaultUploadHandler implements UploadHandlerInterface
{
    public static function createTempFilename(UploadSlot $uploadSlot): string
    {
        return sprintf('%s.finished.part', $uploadSlot->originalFilename);
    }

    public static function ensurePrerequisitesMet(): void
    {
        // There are no prerequisites.
    }

    public function initiate(UploadSlot $uploadSlot): void
    {
        // No initiation needed.
    }

    /**
     * Returns the v2 chunk upload endpoint URL.
     *
     * @param UploadSlot $uploadSlot
     * @param int $chunkNumber
     *
     * @return string
     */
    public function getChunkUploadUrl(UploadSlot $uploadSlot, int $chunkNumber): string
    {
        return route('v2.upload', $uploadSlot->token);
    }

    /**
     * Completes the upload by validating the file and moving it to its intended location.
     *
     * @param CompleteUploadRequest $request
     * @param UploadSlot $uploadSlot
     *
     * @return void
     *
     * @throws FileNotFoundException
     * @throws ValidationException
     */
    public function complete(CompleteUploadRequest $request, UploadSlot $uploadSlot): void
    {
        $diskName = config('chunk-upload.storage.disk');
        $disk = Storage::disk($diskName);
        $filePath = implode(DIRECTORY_SEPARATOR, [config('chunk-upload.storage.chunks'), static::createTempFilename($uploadSlot)]);

        if (!$disk->exists($filePath)) {
            throw new FileNotFoundException(sprintf('No temporary file found at %s. Ensure the chunk upload completed.', $filePath));
        }

        $mimeType = mime_content_type($disk->path($filePath));

        $typeHandler = $uploadSlot->media_type->handler();
        if (!$typeHandler->isMimeTypeValid($mimeType)) {
            throw ValidationException::withMessages([
                'file' => [
                    trans('validation.mimetypes', ['attribute' => 'file', 'values' => $typeHandler->getAllowedMimetypes()])
                ]
            ]);
        }

        $stream = $disk->readStream($filePath);

        $writeSuccess = MediaStorage::ORIGINALS->getDisk()->writeStream($uploadSlot->originalFilePath, $stream);

        fclose($stream);
        $disk->delete($filePath);

        if (!$writeSuccess) {
            throw UnableToWriteFile::atLocation(
                $filePath,
                sprintf('Intended disk: %s.', $diskName)
            );
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
        $diskName = config('chunk-upload.storage.disk');
        $filePath = implode(DIRECTORY_SEPARATOR, [
            config('chunk-upload.storage.chunks'),
            static::createTempFilename($uploadSlot)
        ]);

        $success = Storage::disk($diskName)->delete($filePath);

        if (!$success) {
            throw UnableToDeleteFile::atLocation(
                $filePath,
                sprintf('Intended disk: %s.', $diskName)
            );
        }
    }

    public function getCompletionValidationRules(): array
    {
        return [];
    }
}
