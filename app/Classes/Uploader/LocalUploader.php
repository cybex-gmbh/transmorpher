<?php

namespace App\Classes\Uploader;

use App\Enums\MediaStorage;
use App\Interfaces\UploaderContract;
use App\Models\Media;
use App\Models\UploadSlot;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use RuntimeException;

class LocalUploader implements UploaderContract
{
    /**
     * No-op for local uploads.
     *
     * @param UploadSlot $uploadSlot
     * @return void
     */
    public function initiateUpload(UploadSlot $uploadSlot): void
    {
        // No initiation needed for local uploads.
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
     * Completes local upload by validating the assembled file and moving it to the target key.
     *
     * @param UploadSlot $uploadSlot
     * @param array $completionData
     * @return null
     */
    public function completeUpload(UploadSlot $uploadSlot, array $completionData): ?UploadedFile
    {
        $cacheKey = sprintf('assembled_file_%s', $uploadSlot->token);
        $fileData = Cache::get($cacheKey);

        if ($fileData === null) {
            throw new RuntimeException('No assembled file found for this upload slot. Ensure all chunks have been uploaded.');
        }

        $targetKey = $completionData['target_key'] ?? null;
        $validationRules = $completionData['validation_rules'] ?? null;

        if ($targetKey === null || $validationRules === null) {
            throw new RuntimeException('Missing local completion metadata: target_key and validation_rules are required.');
        }

        $uploadedFile = new UploadedFile(
            $fileData['path'],
            $fileData['original_name'],
            $fileData['mime_type'],
            null,
            true
        );

        try {
            Media::validateMimeType($uploadedFile->getMimeType() ?? 'application/octet-stream', $validationRules);
        } catch (\Throwable $throwable) {
            File::delete($uploadedFile->getRealPath());
            Cache::forget($cacheKey);
            throw $throwable;
        }

        $directory = pathinfo($targetKey, PATHINFO_DIRNAME);
        $filename = pathinfo($targetKey, PATHINFO_BASENAME);

        $writeSuccess = MediaStorage::ORIGINALS->getDisk()->putFileAs($directory, $uploadedFile, $filename);

        if (!$writeSuccess) {
            File::delete($uploadedFile->getRealPath());
            Cache::forget($cacheKey);
            throw new RuntimeException('Could not write assembled local upload to originals storage.');
        }

        File::delete($uploadedFile->getRealPath());
        Cache::forget($cacheKey);

        return null;
    }

    /**
     * Cleans up any cached assembled file for the given upload slot.
     *
     * @param UploadSlot $uploadSlot
     * @return void
     */
    public function abortUpload(UploadSlot $uploadSlot): void
    {
        $cacheKey = sprintf('assembled_file_%s', $uploadSlot->token);
        $fileData = Cache::get($cacheKey);

        if ($fileData !== null) {
            \File::delete($fileData['path']);
            Cache::forget($cacheKey);
        }
    }

    /**
     * Local uploads do not use an upload ID.
     *
     * @param UploadSlot $uploadSlot
     * @return null
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

