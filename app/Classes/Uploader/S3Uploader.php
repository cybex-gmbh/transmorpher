<?php

namespace App\Classes\Uploader;

use App\Enums\MediaStorage;
use App\Interfaces\UploaderContract;
use App\Models\Media;
use App\Models\UploadSlot;
use Aws\S3\S3Client;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class S3Uploader implements UploaderContract
{
    protected ?S3Client $client;
    protected ?string $bucket;

    /**
     * The cache TTL for the upload ID, matching UploadSlot::valid_until (24 hours).
     */
    protected const int CACHE_TTL_HOURS = 24;

    public function __construct(?S3Client $client = null, ?string $bucket = null)
    {
        $this->client = $client;
        $this->bucket = $bucket;
    }

    /**
     * Initiates an S3 multipart upload and stores the upload ID in cache.
     *
     * @param UploadSlot $uploadSlot
     * @return void
     */
    public function initiateUpload(UploadSlot $uploadSlot): void
    {
        $client = $this->getS3Client();
        $bucket = $this->getBucket();
        $key = $this->getObjectKey($uploadSlot);

        $result = $client->createMultipartUpload([
            'Bucket' => $bucket,
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
        $client = $this->getS3Client();
        $bucket = $this->getBucket();
        $key = $this->getObjectKey($uploadSlot);
        $uploadId = $this->getUploadId($uploadSlot);

        $command = $client->getCommand('UploadPart', [
            'Bucket' => $bucket,
            'Key' => $key,
            'UploadId' => $uploadId,
            'PartNumber' => $chunkNumber,
        ]);

        return (string) $client->createPresignedRequest($command, '+24 hours')->getUri();
    }

    /**
     * Completes the S3 multipart upload, validates content-type via HeadObject,
     * and returns null (file is already stored on S3).
     * Throws on validation failure and deletes the S3 object.
     *
     * @param UploadSlot $uploadSlot
     * @param array $completionData
     * @return null
     */
    public function completeUpload(UploadSlot $uploadSlot, array $completionData): ?UploadedFile
    {
        $client = $this->getS3Client();
        $bucket = $this->getBucket();
        $sourceKey = $this->getObjectKey($uploadSlot);
        $uploadId = $this->getUploadId($uploadSlot);

        $client->completeMultipartUpload([
            'Bucket' => $bucket,
            'Key' => $sourceKey,
            'UploadId' => $uploadId,
            'MultipartUpload' => [
                'Parts' => $completionData['parts'],
            ],
        ]);

        $headResult = $client->headObject([
            'Bucket' => $bucket,
            'Key' => $sourceKey,
        ]);

        $contentType = $headResult['ContentType'];
        $mediaType = $uploadSlot->media_type;

        try {
            Media::validateMimeType($contentType, $mediaType->handler()->getValidationRules());
        } catch (ValidationException $e) {
            $client->deleteObject([
                'Bucket' => $bucket,
                'Key' => $sourceKey,
            ]);

            throw $e;
        }

        $targetKey = $completionData['target_key'] ?? $sourceKey;

        if ($targetKey !== $sourceKey) {
            throw new RuntimeException('S3 completion target_key must match the reserved final object key.');
        }

        return null;
    }

    /**
     * Aborts the S3 multipart upload.
     *
     * @param UploadSlot $uploadSlot
     * @return void
     */
    public function abortUpload(UploadSlot $uploadSlot): void
    {
        $client = $this->getS3Client();
        $bucket = $this->getBucket();
        $key = $this->getObjectKey($uploadSlot);
        $uploadId = $this->getUploadId($uploadSlot);

        if ($uploadId === null) {
            return;
        }

        $client->abortMultipartUpload([
            'Bucket' => $bucket,
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
     * @return string
     */
    public function getUploadId(UploadSlot $uploadSlot): ?string
    {
        $uploadId = Cache::get(sprintf('upload_id_%s', $uploadSlot->token));

        if ($uploadId === null && $this->needsUploadId()) {
            throw new RuntimeException(sprintf(
                'No upload ID found in cache for token "%s". The upload may have expired or never been initiated.',
                $uploadSlot->token
            ));
        }

        return $uploadId;
    }

    /**
     * S3 uploads require an upload ID.
     *
     * @return true
     */
    public function needsUploadId(): bool
    {
        return true;
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
     * The key uses the filename provided while reserving the upload slot.
     *
     * @param UploadSlot $uploadSlot
     * @return string
     */
    public function getObjectKey(UploadSlot $uploadSlot): string
    {
        $filename = Cache::get(sprintf('filename_%s', $uploadSlot->token));

        if ($filename === null) {
            throw new RuntimeException(sprintf(
                'No filename found in cache for token "%s". The upload may have expired or was not reserved with a filename.',
                $uploadSlot->token
            ));
        }

        return sprintf('%s/%s/%s', $uploadSlot->User->name, $uploadSlot->identifier, trim($filename));
    }

    /**
     * Resolves the S3 client from the originals disk adapter.
     *
     * @return S3Client
     */
    protected function getS3Client(): S3Client
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $diskName = MediaStorage::ORIGINALS->getDiskName();
        $diskConfig = config(sprintf('filesystems.disks.%s', $diskName));

        if (!is_array($diskConfig) || ($diskConfig['driver'] ?? null) !== 's3') {
            throw new RuntimeException('Originals disk is not configured as an S3 disk.');
        }

        $clientConfig = [
            'version' => 'latest',
            'region' => $diskConfig['region'] ?? config('transmorpher.aws.region'),
        ];

        if (!empty($diskConfig['key']) && !empty($diskConfig['secret'])) {
            $clientConfig['credentials'] = [
                'key' => $diskConfig['key'],
                'secret' => $diskConfig['secret'],
                'token' => $diskConfig['token'] ?? null,
            ];
        }

        if (!empty($diskConfig['endpoint'])) {
            $clientConfig['endpoint'] = $diskConfig['endpoint'];
        }

        if (!empty($diskConfig['use_path_style_endpoint'])) {
            $clientConfig['use_path_style_endpoint'] = true;
        }

        return new S3Client($clientConfig);
    }

    /**
     * Resolves the S3 bucket name from the originals disk configuration.
     *
     * @return string
     */
    protected function getBucket(): string
    {
        if ($this->bucket !== null) {
            return $this->bucket;
        }

        $diskName = MediaStorage::ORIGINALS->getDiskName();

        return config(sprintf('filesystems.disks.%s.bucket', $diskName));
    }
}


