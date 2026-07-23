<?php

namespace App\Classes\UploadHandler;

use App\Enums\MediaStorage;
use App\Http\Requests\V2\CompleteUploadRequest;
use App\Interfaces\UploadHandlerInterface;
use App\Models\UploadSlot;
use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class S3MultipartUploadHandler implements UploadHandlerInterface
{
    protected S3Client $client;
    protected string $bucket;

    /**
     * The cache TTL for the upload ID, matching UploadSlot::valid_until (24 hours).
     */
    protected const int CACHE_TTL_HOURS = 24;

    public function __construct()
    {
        /** @var $disk AwsS3V3Adapter */
        $disk = MediaStorage::ORIGINALS->getDisk();

        $this->client = $disk->getClient();
        $this->bucket = $disk->getConfig()['bucket'];
    }

    /**
     * Ensures that the configured originals disk is an S3 disk.
     *
     * @return void
     *
     * @throws RuntimeException
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
     *
     * @return void
     *
     * @throws AwsException
     * @throws RuntimeException
     */
    public function initiate(UploadSlot $uploadSlot): void
    {
        $result = $this->client->createMultipartUpload([
            'Bucket' => $this->bucket,
            'Key' => $this->getObjectKey($uploadSlot),
        ]);

        $success = Cache::put(
            sprintf('upload_id_%s', $uploadSlot->token),
            $result['UploadId'],
            now()->addHours(self::CACHE_TTL_HOURS)
        );

        if (!$success) {
            throw new RuntimeException(sprintf('Failed to cache S3 Upload ID for upload token %s', $uploadSlot->token));
        }
    }

    /**
     * Returns a presigned URL for uploading a single part.
     *
     * @param UploadSlot $uploadSlot
     * @param int $chunkNumber
     *
     * @return string
     *
     * @throws AwsException
     * @throws RuntimeException
     */
    public function getChunkUploadUrl(UploadSlot $uploadSlot, int $chunkNumber): string
    {
        $command = $this->client->getCommand('UploadPart', [
            'Bucket' => $this->bucket,
            'Key' => $this->getObjectKey($uploadSlot),
            'UploadId' => $this->getUploadId($uploadSlot),
            'PartNumber' => $chunkNumber,
        ]);

        return (string)$this->client->createPresignedRequest($command, '+24 hours')->getUri();
    }

    /**
     * Completes the S3 multipart upload and validates the mime type.
     *
     * Throws on validation failure and then deletes the S3 object.
     *
     * @param CompleteUploadRequest $request
     * @param UploadSlot $uploadSlot
     *
     * @return void
     *
     * @throws AwsException
     * @throws ValidationException
     * @throws RuntimeException
     */
    public function complete(CompleteUploadRequest $request, UploadSlot $uploadSlot): void
    {
        $key = $this->getObjectKey($uploadSlot);
        $uploadId = $this->getUploadId($uploadSlot);

        $parts = $this->getUploadParts($key, $uploadId);

        $this->client->completeMultipartUpload([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'UploadId' => $uploadId,
            'MultipartUpload' => [
                'Parts' => $parts,
            ],
        ]);

        $contentType = mime_content_type(MediaStorage::ORIGINALS->getDisk()->readStream($uploadSlot->originalFilePath));

        $typeHandler = $uploadSlot->media_type->handler();
        if (!$typeHandler->isMimeTypeValid($contentType)) {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            throw ValidationException::withMessages([
                'file' => [
                    trans('validation.mimetypes', ['attribute' => 'file', 'values' => $typeHandler->getAllowedMimetypes()])
                ]
            ]);
        }
    }

    /**
     * Aborts the S3 multipart upload.
     *
     * @param UploadSlot $uploadSlot
     *
     * @return void
     *
     * @throws AwsException
     * @throws RuntimeException
     */
    public function abort(UploadSlot $uploadSlot): void
    {
        $this->client->abortMultipartUpload([
            'Bucket' => $this->bucket,
            'Key' => $this->getObjectKey($uploadSlot),
            'UploadId' => $this->getUploadId($uploadSlot),
        ]);

        Cache::forget(sprintf('upload_id_%s', $uploadSlot->token));
    }

    public function getUploadSlotRequestValidationRules(): array
    {
        return [];
    }

    public function getChunkUrlRequestValidationRules(): array
    {
        return [];
    }

    public function getCompleteRequestValidationRules(): array
    {
        return [];
    }

    public function getAbortRequestValidationRules(): array
    {
        return [];
    }

    /**
     * Returns the S3 upload ID from cache.
     *
     * @param UploadSlot $uploadSlot
     *
     * @return string|null
     *
     * @throws RuntimeException Thrown if the upload ID is not found
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
     * Returns the S3 object key for the given upload slot.
     *
     * The key is resolved through the configured originals disk path, so disk root is respected.
     *
     * @param UploadSlot $uploadSlot
     *
     * @return string
     */
    protected function getObjectKey(UploadSlot $uploadSlot): string
    {
        return MediaStorage::ORIGINALS->getDisk()->path($uploadSlot->originalFilePath);
    }

    /**
     * Gets all uploaded parts from S3.
     *
     * @param string $key
     * @param string|null $uploadId
     *
     * @return array
     *
     * @throws RuntimeException Thrown if no parts were found.
     */
    protected function getUploadParts(string $key, ?string $uploadId): array
    {
        $paginator = $this->client->getPaginator('ListParts', [
            'Bucket' => $this->bucket,
            'Key' => $key,
            'UploadId' => $uploadId,
        ]);

        $parts = [];

        foreach ($paginator as $page) {
            if (!empty($page['Parts'])) {
                foreach ($page['Parts'] as $part) {
                    $parts[] = [
                        'PartNumber' => $part['PartNumber'],
                        // ETag is wrapped in escaped "
                        'ETag' => json_decode($part['ETag']),
                    ];
                }
            }
        }

        if (!count($parts)) {
            throw new RuntimeException(sprintf('No parts have been uploaded for the key %s, with upload id %s.', $key, $uploadId));
        }

        return $parts;
    }
}



