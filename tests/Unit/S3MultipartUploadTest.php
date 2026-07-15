<?php

namespace Tests\Unit;

use App\Classes\Upload\S3MultipartUpload;
use App\Enums\MediaType;
use App\Http\Requests\V2\CompleteUploadRequest;
use App\Models\UploadSlot;
use App\Models\User;
use Aws\Result;
use Aws\S3\S3Client;
use Carbon\CarbonInterface;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class S3MultipartUploadTest extends TestCase
{
    protected S3MultipartUpload $upload;
    protected UploadSlot $uploadSlot;
    protected S3Client $s3Client;
    protected AwsS3V3Adapter $disk;
    protected string $bucket = 'test-bucket';
    protected string $token = 'test-s3-token-123';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('cache.default', 'array');
        Config::set('transmorpher.disks.originals', 's3Originals');

        $this->uploadSlot = new UploadSlot();
        $this->uploadSlot->token = $this->token;
        $this->uploadSlot->identifier = 's3-uploader-test';
        $this->uploadSlot->filename = 'source-file.jpg';
        $this->uploadSlot->media_type = MediaType::IMAGE;
        $this->uploadSlot->setRelation('User', User::factory()->make(['name' => 's3user']));

        $this->s3Client = Mockery::mock(S3Client::class);
        $this->disk = Mockery::mock(AwsS3V3Adapter::class);

        Storage::shouldReceive('disk')
            ->byDefault()
            ->with('s3Originals')
            ->andReturn($this->disk);

        $this->disk->shouldReceive('getClient')
            ->byDefault()
            ->andReturn($this->s3Client);

        $this->disk->shouldReceive('getConfig')
            ->byDefault()
            ->andReturn(['bucket' => $this->bucket]);

        $this->disk->shouldReceive('path')
            ->byDefault()
            ->andReturn($this->expectedKey());

        $this->upload = new S3MultipartUpload();
    }

    protected function expectedKey(): string
    {
        return sprintf('originals/s3user/s3-uploader-test/%s-source-file.jpg', $this->token);
    }

    #[Test]
    public function initiateUploadStoresUploadIdInCache(): void
    {
        $uploadId = 'test-upload-id-abc';

        $this->s3Client
            ->shouldReceive('createMultipartUpload')
            ->once()
            ->with(Mockery::on(fn($args) =>
                $args['Bucket'] === $this->bucket &&
                $args['Key'] === $this->expectedKey()
            ))
            ->andReturn(new Result(['UploadId' => $uploadId]));

        $this->upload->initiate($this->uploadSlot);

        $this->assertEquals($uploadId, Cache::get(sprintf('upload_id_%s', $this->token)));
    }

    #[Test]
    public function initiateUploadCacheTtlIs24Hours(): void
    {
        $uploadId = 'test-upload-id-ttl';

        Cache::shouldReceive('put')
            ->once()
            ->withArgs(fn($key, $value, $ttl) =>
                $key === sprintf('upload_id_%s', $this->token)
                && $value === $uploadId
                && $ttl instanceof CarbonInterface
                && now()->diffInHours($ttl, false) >= 23
                && now()->diffInHours($ttl, false) <= 24
            )->andReturn(true);

        $this->s3Client
            ->shouldReceive('createMultipartUpload')
            ->andReturn(new Result(['UploadId' => $uploadId]));

        $this->upload->initiate($this->uploadSlot);
    }

    #[Test]
    public function getChunkUploadUrlReturnsPresignedUrl(): void
    {
        $uploadId = 'test-upload-id-presign';
        $presignedUrl = 'https://s3.amazonaws.com/test-bucket/uploads/token?presigned';

        Cache::put(sprintf('upload_id_%s', $this->token), $uploadId, now()->addHours(24));

        $mockRequest = Mockery::mock(\Psr\Http\Message\RequestInterface::class);
        $mockUri = Mockery::mock(\Psr\Http\Message\UriInterface::class);
        $mockUri->shouldReceive('__toString')->andReturn($presignedUrl);
        $mockRequest->shouldReceive('getUri')->andReturn($mockUri);

        $mockCommand = Mockery::mock(\Aws\CommandInterface::class);
        $this->s3Client
            ->shouldReceive('getCommand')
            ->once()
            ->with('UploadPart', Mockery::on(fn($args) =>
                $args['Bucket'] === $this->bucket &&
                $args['Key'] === $this->expectedKey() &&
                $args['UploadId'] === $uploadId &&
                $args['PartNumber'] === 1
            ))
            ->andReturn($mockCommand);

        $this->s3Client
            ->shouldReceive('createPresignedRequest')
            ->once()
            ->with($mockCommand, '+24 hours')
            ->andReturn($mockRequest);

        $url = $this->upload->getChunkUploadUrl($this->uploadSlot, 1);

        $this->assertEquals($presignedUrl, $url);
    }

    protected function makeCompleteRequest(array $parts): CompleteUploadRequest
    {
        $request = Mockery::mock(CompleteUploadRequest::class)->makePartial();
        $request->shouldReceive('validated')->with('parts')->andReturn($parts);

        return $request;
    }

    #[Test]
    public function completeUploadCallsCompleteMultipartUpload(): void
    {
        $uploadId = 'test-upload-id-complete';
        $parts = [
            ['PartNumber' => 1, 'ETag' => '"etag1"'],
            ['PartNumber' => 2, 'ETag' => '"etag2"'],
        ];

        Cache::put(sprintf('upload_id_%s', $this->token), $uploadId, now()->addHours(24));

        $this->s3Client
            ->shouldReceive('completeMultipartUpload')
            ->once()
            ->with(Mockery::on(fn($args) =>
                $args['Bucket'] === $this->bucket &&
                $args['Key'] === $this->expectedKey() &&
                $args['UploadId'] === $uploadId &&
                $args['MultipartUpload']['Parts'] === $parts
            ))
            ->andReturn(new Result([]));

        $this->s3Client
            ->shouldReceive('headObject')
            ->once()
            ->with(Mockery::on(fn($args) =>
                $args['Bucket'] === $this->bucket &&
                $args['Key'] === $this->expectedKey()
            ))
            ->andReturn(new Result(['ContentType' => 'image/jpeg']));

        $this->s3Client->shouldNotReceive('copyObject');

        $this->upload->complete($this->makeCompleteRequest($parts), $this->uploadSlot);
    }

    #[Test]
    public function completeUploadIgnoresTargetKeyAndUsesReservedObjectKey(): void
    {
        $uploadId = 'test-upload-id-complete-mismatch';
        $parts = [
            ['PartNumber' => 1, 'ETag' => '"etag1"'],
        ];

        Cache::put(sprintf('upload_id_%s', $this->token), $uploadId, now()->addHours(24));

        $this->s3Client
            ->shouldReceive('completeMultipartUpload')
            ->once()
            ->with(Mockery::on(fn($args) =>
                $args['Bucket'] === $this->bucket &&
                $args['Key'] === $this->expectedKey()
            ))
            ->andReturn(new Result([]));

        $this->s3Client
            ->shouldReceive('headObject')
            ->once()
            ->andReturn(new Result(['ContentType' => 'image/jpeg']));

        $this->upload->complete($this->makeCompleteRequest($parts), $this->uploadSlot);
    }

    #[Test]
    public function completeUploadDeletesS3ObjectAndThrowsOnInvalidMimeType(): void
    {
        $uploadId = 'test-upload-id-fail';
        $parts = [['PartNumber' => 1, 'ETag' => '"etag1"']];

        Cache::put(sprintf('upload_id_%s', $this->token), $uploadId, now()->addHours(24));

        $this->s3Client
            ->shouldReceive('completeMultipartUpload')
            ->once()
            ->andReturn(new Result([]));

        $this->s3Client
            ->shouldReceive('headObject')
            ->once()
            ->andReturn(new Result(['ContentType' => 'application/pdf']));

        $this->s3Client
            ->shouldReceive('deleteObject')
            ->once()
            ->with(Mockery::on(fn($args) =>
                $args['Bucket'] === $this->bucket &&
                $args['Key'] === $this->expectedKey()
            ));

        $this->expectException(ValidationException::class);

        $this->upload->complete($this->makeCompleteRequest($parts), $this->uploadSlot);
    }

    #[Test]
    public function abortUploadCallsAbortMultipartUpload(): void
    {
        $uploadId = 'test-upload-id-abort';

        Cache::put(sprintf('upload_id_%s', $this->token), $uploadId, now()->addHours(24));

        $this->s3Client
            ->shouldReceive('abortMultipartUpload')
            ->once()
            ->with(Mockery::on(fn($args) =>
                $args['Bucket'] === $this->bucket &&
                $args['Key'] === $this->expectedKey() &&
                $args['UploadId'] === $uploadId
            ));

        $this->upload->abort($this->uploadSlot);

        $this->assertNull(Cache::get(sprintf('upload_id_%s', $this->token)));
    }

    #[Test]
    public function abortUploadThrowsWhenNoUploadIdInCache(): void
    {
        $this->expectException(RuntimeException::class);

        $this->upload->abort($this->uploadSlot);
    }

    #[Test]
    public function getCompletionValidationRulesReturnsPartsRules(): void
    {
        $rules = $this->upload->getCompletionValidationRules();

        $this->assertArrayHasKey('parts', $rules);
        $this->assertArrayHasKey('parts.*.PartNumber', $rules);
        $this->assertArrayHasKey('parts.*.ETag', $rules);
    }

}
