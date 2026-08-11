<?php

namespace Tests\v2\Feature;

use App\Classes\UploadHandler\S3MultipartUploadHandler;
use App\Enums\MediaType;
use App\Models\Media;
use App\Models\UploadSlot;
use App\Models\User;
use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Tests\TestCase;
use Transcode;

class S3MultipartUploadTest extends TestCase
{
    protected User $user;
    protected S3Client $s3Client;
    protected AwsS3V3Adapter $disk;
    protected string $bucket = 'test-bucket';
    protected string $v2ApiBaseRoute = '/api/v2';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('cache.default', 'array');
        Config::set('transmorpher.disks.originals', 's3Originals');

        $this->user = User::first() ?: User::factory()->create();
        Sanctum::actingAs($this->user, ['*']);

        $this->s3Client = Mockery::mock(S3Client::class);
        $this->disk = Mockery::mock(AwsS3V3Adapter::class)->makePartial();

        // Called by the S3MultipartUpload constructor
        // and by UploadSlot::setUniqueToken() on every slot creation.
        $this->disk->shouldReceive('getClient')->andReturn($this->s3Client);
        $this->disk->shouldReceive('getConfig')->andReturn(['bucket' => $this->bucket]);
        $this->disk->shouldReceive('path')->andReturnUsing(fn($path) => $path);
        $this->disk->shouldReceive('exists')->andReturn(false);

        Storage::shouldReceive('disk')->with('s3Originals')->andReturn($this->disk);

        $this->app->instance('upload-handler', new S3MultipartUploadHandler());
    }

    #[Test]
    public function canReserveUploadSlot(): void
    {
        $identifier = 'reserve-upload-slot-s3-' . uniqid();

        $this->s3Client->shouldReceive('createMultipartUpload')->once()->andReturn(new Result(['UploadId' => 'test-id']));

        $response = $this->postJson($this->reserveUploadSlotRoute(MediaType::IMAGE), [
            'identifier' => $identifier,
            'filename' => 'test-image.jpg',
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('upload_token'));

        $uploadSlot = UploadSlot::withoutGlobalScopes()->firstWhere('identifier', $identifier);
        $this->assertModelExists($uploadSlot);
        $this->assertSame($this->user->id, $uploadSlot->user_id);
        $this->assertTrue($uploadSlot->is_valid);
    }

    #[Test]
    public function cachesUploadIdAfterReservation(): void
    {
        $identifier = 'upload-id-available-s3-' . uniqid();

        $this->s3Client->shouldReceive('createMultipartUpload')->once()->andReturn(new Result(['UploadId' => 'cached-id']));

        $response = $this->postJson($this->reserveUploadSlotRoute(MediaType::IMAGE), [
            'identifier' => $identifier,
            'filename' => 'test.jpg',
        ]);

        $uploadToken = $response->json('upload_token');
        $this->assertNotNull(Cache::get(sprintf('upload_id_%s', $uploadToken)));
    }

    #[Test]
    public function canGetUploadUrl(): void
    {
        $identifier = 'chunk-upload-url-s3-' . uniqid();
        $presignedUrl = 'https://s3.example.com/chunk-presigned';

        $this->s3Client->shouldReceive('createMultipartUpload')->once()->andReturn(new Result(['UploadId' => 'chunk-id']));
        $this->mockPresignedRequest($presignedUrl);

        $reserveResponse = $this->postJson($this->reserveUploadSlotRoute(MediaType::IMAGE), [
            'identifier' => $identifier,
            'filename' => 'test.jpg',
        ]);

        $uploadSlot = UploadSlot::withoutGlobalScopes()->firstWhere('token', $reserveResponse->json('upload_token'));

        $urlResponse = $this->getJson($this->chunkUploadUrlRoute($uploadSlot, 1));

        $urlResponse->assertOk();
        $this->assertSame($presignedUrl, $urlResponse->json('url'));
    }

    #[Test]
    public function canCompleteUploadAndCreateMedia(): void
    {
        $identifier = 'complete-upload-s3-' . uniqid();

        $this->s3Client->shouldReceive('createMultipartUpload')->once()->andReturn(new Result(['UploadId' => 'complete-id']));

        $reserveResponse = $this->postJson($this->reserveUploadSlotRoute(MediaType::IMAGE), [
            'identifier' => $identifier,
            'filename' => 'source.jpg',
        ]);

        $uploadSlot = UploadSlot::withoutGlobalScopes()->firstWhere('token', $reserveResponse->json('upload_token'));

        $this->mockListParts([['PartNumber' => 1, 'ETag' => '"etag1"']]);
        $this->s3Client->shouldReceive('completeMultipartUpload')->once()->andReturn(new Result([]));
        $this->mockDetectedMimetype('image/jpeg');

        $completeResponse = $this->postJson($this->completeUploadRoute($uploadSlot));

        $completeResponse->assertCreated();
        $this->assertSame('success', $completeResponse->json('state'));

        $media = Media::firstWhere('identifier', $identifier);
        $this->assertModelExists($media);
        $this->assertSame(MediaType::IMAGE, $media->type);

        $version = $media->Versions()->first();
        $this->assertModelExists($version);
        $this->assertSame(1, $version->number);
    }

    #[Test]
    public function failsCompletionWhenS3ReturnsNoParts(): void
    {
        $identifier = 'no-s3-parts-' . uniqid();

        $this->s3Client->shouldReceive('createMultipartUpload')->once()->andReturn(new Result(['UploadId' => 'no-parts-id']));

        $reserveResponse = $this->postJson($this->reserveUploadSlotRoute(MediaType::IMAGE), [
            'identifier' => $identifier,
            'filename' => 'test.jpg',
        ]);

        $uploadSlot = UploadSlot::firstWhere('token', $reserveResponse->json('upload_token'));

        $this->mockListParts([]);
        $this->s3Client->shouldNotReceive('completeMultipartUpload');

        $completeResponse = $this->postJson($this->completeUploadRoute($uploadSlot), data: []);
        $completeResponse->assertStatus(500);

        $this->assertNull(Media::firstWhere('identifier', $identifier));
    }

    #[Test]
    public function failsAndRollsBackUploadWithInvalidMimeType(): void
    {
        $identifier = 'invalid-mime-type-s3-' . uniqid();

        $this->s3Client->shouldReceive('createMultipartUpload')->once()->andReturn(new Result(['UploadId' => 'invalid-mime-id']));

        $reserveResponse = $this->postJson($this->reserveUploadSlotRoute(MediaType::IMAGE), [
            'identifier' => $identifier,
            'filename' => 'invalid.txt',
        ]);
        $reserveResponse->assertOk();

        $uploadSlot = UploadSlot::withoutGlobalScopes()->firstWhere('token', $reserveResponse->json('upload_token'));

        $this->mockListParts([['PartNumber' => 1, 'ETag' => '"etag1"']]);
        $this->s3Client->shouldReceive('completeMultipartUpload')->once()->andReturn(new Result([]));
        $this->mockDetectedMimetype('text/plain');
        $this->s3Client->shouldReceive('deleteObject')->once(); // S3 object deleted on validation failure

        $completeResponse = $this->postJson($this->completeUploadRoute($uploadSlot));
        $completeResponse->assertStatus(422);

        $this->assertNull(Media::firstWhere('identifier', $identifier));
    }

    #[Test]
    public function canAbortUpload(): void
    {
        $identifier = 'abort-upload-s3-' . uniqid();

        $this->s3Client->shouldReceive('createMultipartUpload')->once()->andReturn(new Result(['UploadId' => 'abort-id']));

        $reserveResponse = $this->postJson($this->reserveUploadSlotRoute(MediaType::IMAGE), [
            'identifier' => $identifier,
            'filename' => 'test.jpg',
        ]);

        $uploadSlot = UploadSlot::withoutGlobalScopes()->firstWhere('token', $reserveResponse->json('upload_token'));

        $this->s3Client->shouldReceive('abortMultipartUpload')->once();

        $abortResponse = $this->deleteJson($this->abortUploadRoute($uploadSlot));
        $abortResponse->assertOk();
        $this->assertSame('aborted', $abortResponse->json('state'));

        $uploadSlot->refresh();
        $this->assertFalse($uploadSlot->is_valid);
    }


    #[Test]
    public function createsVersionsForSameMediaForMultipleUploadsWithSameIdentifier(): void
    {
        $identifier = 'versions-s3-' . uniqid();

        $this->s3Client->shouldReceive('createMultipartUpload')->once()->andReturn(new Result(['UploadId' => 'versions-id']));

        // First upload
        $res1 = $this->postJson($this->reserveUploadSlotRoute(MediaType::IMAGE), [
            'identifier' => $identifier,
            'filename' => 'v1.jpg',
        ]);

        $this->mockListParts([['PartNumber' => 1, 'ETag' => '"etag1"']]);
        $this->s3Client->shouldReceive('completeMultipartUpload')->once()->andReturn(new Result([]));
        $this->mockDetectedMimetype('image/jpeg');

        $slot1 = UploadSlot::firstWhere('token', $res1->json('upload_token'));
        $this->postJson($this->completeUploadRoute($slot1))->assertSuccessful();

        $media = Media::firstWhere('identifier', $identifier);
        $this->assertSame(1, $media->Versions()->count());

        $this->s3Client->shouldReceive('createMultipartUpload')->once()->andReturn(new Result(['UploadId' => 'versions-id']));

        // Second upload for same identifier creates a new version
        $res2 = $this->postJson($this->reserveUploadSlotRoute(MediaType::IMAGE), [
            'identifier' => $identifier,
            'filename' => 'v2.jpg',
        ]);

        $this->mockListParts([['PartNumber' => 1, 'ETag' => '"etag1"']]);
        $this->s3Client->shouldReceive('completeMultipartUpload')->once()->andReturn(new Result([]));
        $this->mockDetectedMimetype('image/jpeg');

        $slot2 = UploadSlot::firstWhere('token', $res2->json('upload_token'));
        $this->postJson($this->completeUploadRoute($slot2))->assertSuccessful();

        $media->refresh();
        $this->assertSame(2, $media->Versions()->count());
        $this->assertSame(2, $media->latestVersion->number);
    }

    #[Test]
    public function failsOnExpiredUploadId(): void
    {
        $identifier = 'expired-upload-id-s3-' . uniqid();

        $this->s3Client->shouldReceive('createMultipartUpload')->once()->andReturn(new Result(['UploadId' => 'expired-id']));

        $reserveResponse = $this->postJson($this->reserveUploadSlotRoute(MediaType::IMAGE), [
            'identifier' => $identifier,
            'filename' => 'test.jpg',
        ]);

        $uploadToken = $reserveResponse->json('upload_token');
        $uploadSlot = UploadSlot::firstWhere('token', $uploadToken);

        // Simulate 24-hour cache expiration
        Cache::forget(sprintf('upload_id_%s', $uploadToken));

        $completeResponse = $this->postJson($this->completeUploadRoute($uploadSlot));
        $completeResponse->assertStatus(500);

        $this->assertNull(Media::firstWhere('identifier', $identifier));
    }

    #[Test]
    public function canUploadDocument(): void
    {
        $identifier = 'upload-s3-document-' . uniqid();

        $this->s3Client->shouldReceive('createMultipartUpload')->once()->andReturn(new Result(['UploadId' => 'pdf-id']));

        $reserveResponse = $this->postJson($this->reserveUploadSlotRoute(MediaType::DOCUMENT), [
            'identifier' => $identifier,
            'filename' => 'document.pdf',
        ]);

        $uploadSlot = UploadSlot::firstWhere('token', $reserveResponse->json('upload_token'));

        $this->mockListParts([['PartNumber' => 1, 'ETag' => '"etag1"']]);
        $this->s3Client->shouldReceive('completeMultipartUpload')->once()->andReturn(new Result([]));
        $this->mockDetectedMimetype('application/pdf');

        $completeResponse = $this->postJson($this->completeUploadRoute($uploadSlot));
        $completeResponse->assertCreated();

        $media = Media::firstWhere('identifier', $identifier);
        $this->assertSame(MediaType::DOCUMENT, $media->type);
    }

    #[Test]
    public function canUploadVideo(): void
    {
        $identifier = 'upload-s3-video-' . uniqid();

        $this->s3Client->shouldReceive('createMultipartUpload')->once()->andReturn(new Result(['UploadId' => 'video-id']));

        // Video handler should dispatch transcoding and return PROCESSING on success.
        Transcode::shouldReceive('createJob')->once()->andReturn(true);

        $reserveResponse = $this->postJson($this->reserveUploadSlotRoute(MediaType::VIDEO), [
            'identifier' => $identifier,
            'filename' => 'video.mp4',
        ]);

        $uploadSlot = UploadSlot::firstWhere('token', $reserveResponse->json('upload_token'));

        $this->mockListParts([['PartNumber' => 1, 'ETag' => '"etag1"']]);
        $this->s3Client->shouldReceive('completeMultipartUpload')->once()->andReturn(new Result([]));
        $this->mockDetectedMimetype('video/mp4');

        $completeResponse = $this->postJson($this->completeUploadRoute($uploadSlot));
        $completeResponse->assertCreated();
        $this->assertSame('processing', $completeResponse->json('state'));

        $media = Media::firstWhere('identifier', $identifier);
        $this->assertModelExists($media);
        $this->assertSame(MediaType::VIDEO, $media->type);
    }

    protected function mockPresignedRequest(string $url = 'https://s3.example.com/presigned-url'): void
    {
        $mockUri = Mockery::mock(UriInterface::class);
        $mockUri->shouldReceive('__toString')->andReturn($url);

        $mockRequest = Mockery::mock(RequestInterface::class);
        $mockRequest->shouldReceive('getUri')->andReturn($mockUri);

        $this->s3Client->shouldReceive('getCommand')->with('UploadPart', Mockery::type('array'))->andReturn(Mockery::mock(CommandInterface::class));
        $this->s3Client->shouldReceive('createPresignedRequest')->andReturn($mockRequest);
    }

    protected function mockDetectedMimetype(string $mimeType): void
    {
        $payload = match ($mimeType) {
            'image/jpeg' => "\xFF\xD8\xFF\xE0JFIF",
            'application/pdf' => "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n",
            'video/mp4' => "\x00\x00\x00\x18ftypmp42",
            default => 'plain text payload',
        };

        $this->disk
            ->shouldReceive('readStream')
            ->once()
            ->andReturnUsing(fn() => fopen('data://text/plain;base64,' . base64_encode($payload), 'r'));
    }

    protected function mockListParts(array $parts): void
    {
        $this->s3Client
            ->shouldReceive('getPaginator')
            ->once()
            ->with('ListParts', Mockery::type('array'))
            ->andReturn(new \ArrayIterator([
                ['Parts' => $parts],
            ]));
    }

    protected function reserveUploadSlotRoute(MediaType $mediaType): string
    {
        return sprintf('%s/%s/reserveUploadSlot', $this->v2ApiBaseRoute, $mediaType->value);
    }

    protected function chunkUploadUrlRoute(UploadSlot $uploadSlot, int $chunkNumber): string
    {
        return sprintf('%s/upload/%s/chunkUrl/%d', $this->v2ApiBaseRoute, $uploadSlot->token, $chunkNumber);
    }

    protected function completeUploadRoute(UploadSlot $uploadSlot): string
    {
        return sprintf('%s/upload/%s/complete', $this->v2ApiBaseRoute, $uploadSlot->token);
    }

    protected function abortUploadRoute(UploadSlot $uploadSlot): string
    {
        return sprintf('%s/upload/%s', $this->v2ApiBaseRoute, $uploadSlot->token);
    }
}
