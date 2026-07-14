<?php

namespace App\Interfaces;

use App\Http\Requests\V2\CompleteUploadRequest;
use App\Models\UploadSlot;
use Illuminate\Validation\ValidationException;
use Throwable;

interface UploadContract
{
    /**
     * Ensures runtime prerequisites are met, such as the correct disk driver.
     *
     * Should throw on failure.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function ensurePrerequisitesMet(): void;

    /**
     * Initiates the upload process (e.g. creating S3 multipart upload).
     *
     * Should throw on failure.
     *
     * @param UploadSlot $uploadSlot
     *
     * @return void
     *
     * @throws Throwable
     */
    public function initiate(UploadSlot $uploadSlot): void;

    /**
     * Returns a URL for uploading a single chunk.
     *
     * Should throw on failure.
     *
     * @param UploadSlot $uploadSlot
     * @param int $chunkNumber
     *
     * @return string
     *
     * @throws Throwable
     */
    public function getChunkUploadUrl(UploadSlot $uploadSlot, int $chunkNumber): string;

    /**
     * Completes the upload process. This includes:
     *   - Validating the uploaded file for an allowed mime type
     *   - Moving/storing the uploaded file to the final destination
     *
     * Should throw on failure.
     * Should throw a {@link ValidationException} when validation fails, such as when the mime type is not allowed.
     *
     * @param CompleteUploadRequest $request
     * @param UploadSlot $uploadSlot
     *
     * @return void
     *
     * @throws Throwable
     * @throws ValidationException
     */
    public function complete(CompleteUploadRequest $request, UploadSlot $uploadSlot): void;

    /**
     * Aborts the upload process.
     *
     * Should throw on failure.
     *
     * @param UploadSlot $uploadSlot
     *
     * @return void
     */
    public function abort(UploadSlot $uploadSlot): void;

    /**
     * Returns Laravel validation rules for the {@link CompleteUploadRequest} for the complete endpoint request body.
     *
     * @return array
     */
    public function getCompletionValidationRules(): array;
}


