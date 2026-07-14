<?php

namespace App\Classes\Upload;

use App\Enums\MediaStorage;
use App\Http\Requests\V2\CompleteUploadRequest;
use App\Interfaces\UploadContract;
use App\Models\Media;
use App\Models\UploadSlot;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class S3MultipartUpload implements UploadContract
{
    protected S3Client $client;
    protected string $bucket;

    /**
     * The cache TTL for the upload ID, matching UploadSlot::valid_until (24 hours).
     */
    protected const int CACHE_TTL_HOURS = 24;

    public function __construct()
    {
        $disk = MediaStorage::ORIGINALS->getDisk();
        $this->client = $disk->getClient();
        $this->bucket = $disk->getConfig()['bucket'];
    }

    /**
     * Ensure the configured originals disk is an S3 disk.
     *
     * @return void
     */
    public static function ensurePrerequisitesMet(): void
    {
        $diskName = MediaStorage::ORIGINALS->getDiskName();
        $diskDriver = config(sprintf('filesystems.disks.%s.driver', $diskName));

        if ($diskDriver !== 's3') {
            throw new RuntimeException('Originals disk is not configured as an S3 disk.');
        }
    }

    /**
     * Initiates an S3 multipart upload and stores the upload ID in cache.
     *
     * @param UploadSlot $uploadSlot
     * @return void
     */
    public function initiate(UploadSlot $uploadSlot): void
    {
        $key = $this->getObjectKey($uploadSlot);

        $result = $this->client->createMultipartUpload([
            'Bucket' => $this->bucket,
            'Key' => $key,
        ]);

        Cache::put(
            sprintf('upload_id_%s', $uploadSlot->token),
            $result['UploadId'],
            now()->addHours(self::CACHE_TTL_HOURS)
        );
    }

    /**
     * Returns a presigned URL for uploading a single part.
     *
     * @param UploadSlot $uploadSlot
     * @param int $chunkNumber
     * @return string
     */
    public function getChunkUploadUrl(UploadSlot $uploadSlot, int $chunkNumber): string
    {
        $key = $this->getObjectKey($uploadSlot);
        $uploadId = $this->getUploadId($uploadSlot);

        $command = $this->client->getCommand('UploadPart', [
            'Bucket' => $this->bucket,
            'Key' => $key,
            'UploadId' => $uploadId,
            'PartNumber' => $chunkNumber,
        ]);

        return (string) $this->client->createPresignedRequest($command, '+24 hours')->getUri();
    }

    /**
     * Completes the S3 multipart upload, validates content-type via HeadObject,
     * and returns null (file is already stored on S3).
     * Throws on validation failure and deletes the S3 object.
     *
     * @param CompleteUploadRequest $request
     * @param UploadSlot $uploadSlot
     *
     * @return void
     */
    public function complete(CompleteUploadRequest $request, UploadSlot $uploadSlot): void
    {
        $sourceKey = $this->getObjectKey($uploadSlot);
        $uploadId = $this->getUploadId($uploadSlot);

        $this->client->completeMultipartUpload([
            'Bucket' => $this->bucket,
            'Key' => $sourceKey,
            'UploadId' => $uploadId,
            'MultipartUpload' => [
                'Parts' => $request->validated('parts'),
            ],
        ]);

        $headResult = $this->client->headObject([
            'Bucket' => $this->bucket,
            'Key' => $sourceKey,
        ]);

        $contentType = $headResult['ContentType'];
        $mediaType = $uploadSlot->media_type;

        try {
            Media::validateMimeType($contentType, $mediaType->handler()->getValidationRules());
        } catch (ValidationException $e) {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $sourceKey,
            ]);

            throw $e;
        }

    }

    /**
     * Aborts the S3 multipart upload.
     *
     * @param UploadSlot $uploadSlot
     * @return void
     */
    public function abort(UploadSlot $uploadSlot): void
    {
        $key = $this->getObjectKey($uploadSlot);
        $uploadId = $this->getUploadId($uploadSlot);

        if ($uploadId === null) {
            return;
        }

        $this->client->abortMultipartUpload([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'UploadId' => $uploadId,
        ]);

        Cache::forget(sprintf('upload_id_%s', $uploadSlot->token));
    }

    /**
     * Returns the S3 upload ID from cache.
     * Throws if the upload ID is not found.
     *
     * @param UploadSlot $uploadSlot
     * @return string|null
     */
    protected function getUploadId(UploadSlot $uploadSlot): ?string
    {
        $uploadId = Cache::get(sprintf('upload_id_%s', $uploadSlot->token));

        if ($uploadId === null) {
            throw new RuntimeException(sprintf(
                'No upload ID found in cache for token "%s". The upload may have expired or never been initiated.',
                $uploadSlot->token
            ));
        }

        return $uploadId;
    }

    /**
     * Returns S3 multipart upload completion validation rules.
     *
     * @return array
     */
    public function getCompletionValidationRules(): array
    {
        return [
            'parts' => 'required|array',
            'parts.*.PartNumber' => 'required|integer',
            'parts.*.ETag' => 'required|string',
        ];
    }

    /**
     * Returns the S3 object key for the given upload slot.
     *
     * The key is resolved through the configured originals disk path, so disk root is respected.
     *
     * @param UploadSlot $uploadSlot
     * @return string
     */
    protected function getObjectKey(UploadSlot $uploadSlot): string
    {
        return MediaStorage::ORIGINALS->getDisk()->path($uploadSlot->originalFilePath);
    }

}



