<?php

namespace Tests\v2\Feature;

use App\Enums\MediaType;
use App\Models\User;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Exceptions;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;
use Transcode;

class TranscodeVersionTest extends TestCase
{
    use RefreshDatabase;

    protected Version $version;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('transmorpher.queue.video_transcoding.connection', 'database');
        config()->set('transmorpher.queue.video_transcoding.use_sqs_fifo', false);
        Exceptions::fake();

        $media = User::factory()->create()->Media()->create([
            'identifier' => 'batch-video',
            'type' => MediaType::VIDEO,
        ]);
        $this->version = $media->Versions()->create(['number' => 1, 'filename' => 'video.mp4']);
    }

    #[Test]
    public function dispatchesVersion(): void
    {
        Bus::fake();

        $this->artisan('transcode:version', [
            'versionId' => $this->version->getKey(),
            'uploadToken' => 'upload-token',
        ])->assertSuccessful();

        Bus::assertDispatchedSync(Transcode::getJobClass(), fn($job): bool => $job->jobMetadata() === [
                'version_id' => (string)$this->version->getKey(),
                'upload_token' => 'upload-token',
            ]);
    }

    #[Test]
    public function rejectsMissingVersion(): void
    {
        Bus::fake();

        $this->artisan('transcode:version', [
            'versionId' => '999999',
            'uploadToken' => 'upload-token',
        ])->assertFailed();

        Bus::assertNotDispatchedSync(Transcode::getJobClass());
    }

    #[Test]
    public function rejectsNonVideoMedia(): void
    {
        Bus::fake();

        foreach ([MediaType::IMAGE, MediaType::DOCUMENT] as $type) {
            $this->version->Media->update(['type' => $type]);

            $this->artisan('transcode:version', [
                'versionId' => $this->version->getKey(),
                'uploadToken' => 'upload-token',
            ])->assertFailed();
        }

        Bus::assertNothingDispatched();
        $this->assertModelExists($this->version);
    }

    #[Test]
    public function reportsFailure(): void
    {
        $failure = new RuntimeException('Encoding failed');
        Bus::shouldReceive('dispatchSync')->once()->andThrow($failure);

        $this->artisan('transcode:version', [
            'versionId' => $this->version->getKey(),
            'uploadToken' => 'upload-token',
        ])->assertFailed();

        Exceptions::assertReported(fn(RuntimeException $exception): bool => $exception === $failure);
    }
}
