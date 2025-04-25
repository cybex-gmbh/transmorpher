<?php

namespace Tests\Unit;

use App\Console\Commands\PurgeDerivatives;
use App\Enums\ClientNotification;
use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Enums\ResponseState;
use App\Helpers\SodiumHelper;
use App\Jobs\ClientPurgeNotification;
use App\Models\Media;
use App\Models\UploadSlot;
use Artisan;
use File;
use Http;
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

    protected string $identifier = 'testVideo';
    protected string $mediaName = 'video.mp4';
    protected ResponseState $versionSetSuccessful = ResponseState::VIDEO_VERSION_SET;
    protected MediaType $mediaType = MediaType::VIDEO;

    protected function setUp(): void
    {
        parent::setUp();

        $this->derivativesDisk ??= Storage::persistentFake(MediaStorage::VIDEO_DERIVATIVES->getDiskName());
    }

    protected function uploadVideo(): TestResponse
    {
        $uploadToken = $this->reserveUploadSlot()->json('upload_token');

        return $this->post(route('v1.upload', [$uploadToken]), [
            'file' => UploadedFile::fake()->createWithContent('video.mp4', File::get(base_path('tests/data/test.mp4'))),
            'identifier' => $this->identifier
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
        $media = $this->user->Media()->create(['identifier' => $this->identifier, 'type' => $uploadSlot->media_type]);

        $outdatedVersion = $media->Versions()->create(['number' => 1, 'filename' => sprintf('1-%s', $this->mediaName)]);
        $media->Versions()->create(['number' => 2, 'filename' => sprintf('2-%s', $this->mediaName)]);

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

        $this->derivativesDisk->assertExists($media->videoDerivativeFilePath('mp4') . '.mp4');
        $this->derivativesDisk->assertExists($media->videoDerivativeFilePath('hls') . '.m3u8');
        $this->derivativesDisk->assertExists($media->videoDerivativeFilePath('dash') . '.mpd');
    }

    #[Test]
    public function ensureVersionCanBeSet()
    {
        Queue::fake();
        $uploadResponse = $this->uploadVideo();
        Queue::assertPushed(Transcode::getJobClass());

        $media = Media::whereIdentifier($uploadResponse['identifier'])->first();
        $version = $media->Versions()->whereNumber($uploadResponse['version'])->first();

        Queue::fake();
        $setVersionResponse = $this->patchJson(route('v1.setVersion', [$media, $version]));
        Queue::assertPushed(Transcode::getJobClass());

        $setVersionResponse->assertOk();
        $setVersionResponse->assertJsonFragment(['state' => $this->versionSetSuccessful->getState()->value, 'message' => $this->versionSetSuccessful->getMessage()]);

        $setVersion = $version->Media->Versions()->firstWhere('number', $setVersionResponse->json('version'));
        $this->assertModelExists($setVersion);
        $this->assertNotEquals($setVersion, $version);
        $this->assertEquals($setVersion->filename, $version->filename);

        return $setVersion;
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
