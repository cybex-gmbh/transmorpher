<?php

namespace App\Interfaces;

use App\Models\UploadSlot;

interface UploaderContract
{
    /**
     * Initiates the upload process (e.g. S3 multipart upload).
     * UploadSlot creation is NOT handled here.
     *
     * @param UploadSlot $uploadSlot
     * @return void
     */
    public function initiateUpload(UploadSlot $uploadSlot): void;

    /**
     * Returns a (signed) URL for uploading a single chunk.
     * For local uploads this always points to the same chunk-upload endpoint.
     *
     * @param UploadSlot $uploadSlot
     * @param int $chunkNumber
     * @return string
     */
    public function getChunkUploadUrl(UploadSlot $uploadSlot, int $chunkNumber): string;

    /**
     * Completes the upload process.
     * Implementations are responsible for validating and moving/storing the uploaded file
     * into its final destination.
     *
     * @param UploadSlot $uploadSlot
     * @param array $completionData Validated payload from the complete endpoint.
     * @return void
     */
    public function completeUpload(UploadSlot $uploadSlot, array $completionData): void;

    /**
     * Aborts the upload process for the given upload slot.
     *
     * @param UploadSlot $uploadSlot
     * @return void
     */
    public function abortUpload(UploadSlot $uploadSlot): void;

    /**
     * Returns the upload ID for the given upload slot if any.
     * Reads from cache (key: upload_id_{token}).
     * Throws if needsUploadId() is true and no ID is found.
     *
     * @param UploadSlot $uploadSlot
     * @return string|null
     */
    public function getUploadId(UploadSlot $uploadSlot): ?string;

    /**
     * Returns true if the uploader implementation requires an upload ID.
     *
     * @return bool
     */
    public function needsUploadId(): bool;

    /**
     * Returns Laravel validation rules for the complete endpoint request body.
     *
     * @return array
     */
    public function getCompletionValidationRules(): array;
}

