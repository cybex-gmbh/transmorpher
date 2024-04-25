<?php

namespace Tests\Unit;

use App\Console\Commands\PurgeDerivatives;
use App\Enums\ClientNotification;
use App\Enums\MediaStorage;
use App\Enums\ResponseState;
use App\Helpers\SodiumHelper;
use App\Jobs\ClientPurgeNotification;
use App\Models\Media;
use App\Models\UploadSlot;
use Artisan;
use File;
use Http;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Queue;
use Storage;
use Tests\MediaTest;
use Transcode;

class VideoTest extends MediaTest
{
    use RefreshDatabase;

    protected const IDENTIFIER = 'testVideo';
    protected const VIDEO_NAME = 'video.mp4';
    protected Filesystem $videoDerivativesDisk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->videoDerivativesDisk ??= Storage::persistentFake(config(sprintf('transmorpher.disks.%s', MediaStorage::VIDEO_DERIVATIVES->value)));
    }

    /**
     * @return TestResponse
     */
    protected function reserveUploadSlot(): TestResponse
    {
        return $this->json('POST', route('v1.reserveVideoUploadSlot'), [
            'identifier' => self::IDENTIFIER,
        ]);
    }

    #[Test]
    public function ensureVideoUploadSlotCanBeReserved()
    {
        $reserveUploadSlotResponse = $this->reserveUploadSlot();

        $reserveUploadSlotResponse->assertOk();

        return $reserveUploadSlotResponse->json('upload_token');
    }

    protected function uploadVideo(): TestResponse
    {
        $uploadToken = $this->reserveUploadSlot()->json('upload_token');

        return $this->post(route('v1.upload', [$uploadToken]), [
            'file' => UploadedFile::fake()->createWithContent('video.mp4', File::get(base_path('tests/data/test.mp4'))),
            'identifier' => self::IDENTIFIER
        ]);
    }

    #[Test]
    public function ensureVideoCanBeUploaded()
    {
        Queue::fake();

        $uploadResponse = $this->uploadVideo();

        $uploadResponse->assertCreated();
        Queue::assertPushed(Transcode::getJobClass());

        $media = Media::whereIdentifier($uploadResponse['identifier'])->first();
        $version = $media->Versions()->whereNumber($uploadResponse['version'])->first();

        $this->originalsDisk->assertExists($version->originalFilePath());
    }

    #[Test]
    public function ensureTranscodingIsAbortedWhenNewerVersionExists()
    {
        $uploadToken = $this->reserveUploadSlot()->json('upload_token');
        $uploadSlot = UploadSlot::firstWhere('token', $uploadToken);
        $media = $this->user->Media()->create(['identifier' => self::IDENTIFIER, 'type' => $uploadSlot->media_type]);

        $outdatedVersion = $media->Versions()->create(['number' => 1, 'filename' => sprintf('1-%s', self::VIDEO_NAME)]);
        $media->Versions()->create(['number' => 2, 'filename' => sprintf('2-%s', self::VIDEO_NAME)]);

        Http::fake();

        Transcode::createJob($outdatedVersion, $uploadSlot);

        $request = Http::recorded()[0][0];
        $transcodingResult = json_decode(SodiumHelper::decrypt($request->data()['signed_notification']), true);

        $this->assertEquals(ResponseState::TRANSCODING_ABORTED->getState()->value, $transcodingResult['state']);
        $this->assertEquals(ResponseState::TRANSCODING_ABORTED->getMessage(), $transcodingResult['message']);
    }

    #[Test]
    public function ensureTranscodingWorks()
    {
        $uploadResponse = $this->uploadVideo();

        $media = Media::whereIdentifier($uploadResponse['identifier'])->first();
        $version = $media->Versions()->whereNumber($uploadResponse['version'])->first();
        $uploadSlot = UploadSlot::withoutGlobalScopes()->whereToken($uploadResponse['upload_token'])->first();

        Http::fake([
            $this->user->api_url => Http::response()
        ]);

        $this->assertTrue(Transcode::createJob($version, $uploadSlot));

        Http::assertSent(function (Request $request) {
            $decryptedNotification = json_decode(SodiumHelper::decrypt($request['signed_notification']), true);

            return $request->url() == $this->user->api_url
                && $decryptedNotification['notification_type'] == ClientNotification::VIDEO_TRANSCODING->value
                && $decryptedNotification['state'] == ResponseState::TRANSCODING_SUCCESSFUL->getState()->value;
        });

        $this->videoDerivativesDisk->assertExists($media->videoDerivativeFilePath('mp4') . '.mp4');
        $this->videoDerivativesDisk->assertExists($media->videoDerivativeFilePath('hls') . '.m3u8');
        $this->videoDerivativesDisk->assertExists($media->videoDerivativeFilePath('dash') . '.mpd');
    }

    #[Test]
    public function ensureVideoDerivativesArePurged()
    {
        $uploadResponse = $this->uploadVideo();

        $media = Media::whereIdentifier($uploadResponse['identifier'])->first();
        $version = $media->currentVersion;

        $versionNumberBeforePurging = $version->number;

        Queue::fake();
        Http::fake([
            $this->user->api_url => Http::response()
        ]);

        Artisan::call(PurgeDerivatives::class, ['--video' => true]);

        $this->assertTrue($versionNumberBeforePurging + 1 == $version->refresh()->number);
        Queue::assertPushed(Transcode::getJobClass());
        Queue::assertPushed(ClientPurgeNotification::class);
    }
}
