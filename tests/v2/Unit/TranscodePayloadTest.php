<?php

namespace Tests\v2\Unit;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Jobs\ClientPurgeNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Transcode;

class TranscodePayloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(MediaStorage::ORIGINALS->getDiskName());

        config()->set('transmorpher.queue.video_transcoding.connection', 'database');
        config()->set('transmorpher.queue.video_transcoding.use_sqs_fifo', false);
        config()->set('transmorpher.queue.client_notifications.connection', 'database');
        config()->set('transmorpher.queue.client_notifications.use_sqs_fifo', false);
    }

    #[Test]
    public function addsTranscodeMetadata(): void
    {
        $user = User::factory()->create();
        $media = $user->Media()->create(['identifier' => 'payload-video', 'type' => MediaType::VIDEO]);
        $slot = $user->UploadSlots()->create([
            'identifier' => $media->identifier, 'filename' => 'video.mp4', 'media_type' => MediaType::VIDEO,
        ]);
        $version = $media->Versions()->create(['number' => 7, 'filename' => $slot->originalFilename]);

        $this->assertTrue(Transcode::createJob($version, $slot));

        $body = json_decode(DB::table('jobs')->value('payload'), true);

        $this->assertSame(['version_id' => (string)$version->getKey(), 'upload_token' => $slot->token], $body['meta']);
    }

    #[Test]
    public function doesNotAddMetadataToUnrelatedJobs(): void
    {
        ClientPurgeNotification::dispatch(User::factory()->create(), 1);

        $body = json_decode(DB::table('jobs')->value('payload'), true);

        $this->assertArrayNotHasKey('meta', $body);
    }
}
