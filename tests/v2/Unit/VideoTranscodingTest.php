<?php

namespace Tests\v2\Unit;

use App\Enums\ClientNotification;
use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Enums\ResponseState;
use App\Helpers\SodiumHelper;
use App\Models\UploadSlot;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\v2\Support\MediaHelper;
use Transcode;

class VideoTranscodingTest extends MediaHelper
{
    protected MediaType $mediaType = MediaType::VIDEO;
    protected MediaStorage $derivativesStorage = MediaStorage::VIDEO_DERIVATIVES;
    protected ResponseState $versionSetSuccessfulState = ResponseState::VIDEO_VERSION_SET;
    protected string $identifier = 'test-video-transcoding';
    protected string $mediaFileFilePath = 'tests/data/test.mp4';

    #[Test]
    public function transcodesVideoSuccessfully(): void
    {
        Http::fake([
            $this->user->api_url => Http::response(),
        ]);

        $version = $this->performUpload();
        $media = $version->Media;

        Http::assertSent(function (Request $request): bool {
            $decryptedNotification = json_decode(SodiumHelper::decrypt($request['signed_notification']), true);

            return $request->url() === $this->user->api_url
                && $decryptedNotification['notification_type'] === ClientNotification::VIDEO_TRANSCODING->value
                && $decryptedNotification['state'] === ResponseState::TRANSCODING_SUCCESSFUL->getState()->value;
        });

        $this->derivativesDisk->assertExists($media->videoDerivativeFilePath('mp4', 'video.mp4'));
        $this->derivativesDisk->assertExists($media->videoDerivativeFilePath('hls', 'video.m3u8'));
        $this->derivativesDisk->assertExists($media->videoDerivativeFilePath('dash', 'video.mpd'));

        $version->refresh();
        $this->assertTrue((bool)$version->processed);
    }

    #[Test]
    public function abortsTranscodingWhenNewerVersionExists(): void
    {
        $reserveResponse = $this->reserveUploadSlot();
        $reserveResponse->assertOk();

        $uploadSlot = UploadSlot::withoutGlobalScopes()->firstWhere('token', $reserveResponse->json('upload_token'));
        $this->assertModelExists($uploadSlot);

        $media = $this->user->Media()->create([
            'identifier' => $this->identifier,
            'type' => $this->mediaType,
        ]);

        $outdatedVersion = $media->Versions()->create([
            'number' => 1,
            'filename' => $uploadSlot->originalFilename,
        ]);

        $media->Versions()->create([
            'number' => 2,
            'filename' => $uploadSlot->originalFilename,
        ]);

        Http::fake([
            $this->user->api_url => Http::response(),
        ]);

        $this->assertTrue(Transcode::createJob($outdatedVersion, $uploadSlot));

        $request = Http::recorded()[0][0];
        $transcodingResult = json_decode(SodiumHelper::decrypt($request->data()['signed_notification']), true);

        $this->assertEquals(ResponseState::TRANSCODING_ABORTED->getState()->value, $transcodingResult['state']);
        $this->assertEquals(ResponseState::TRANSCODING_ABORTED->getMessage(), $transcodingResult['message']);
        $this->assertModelMissing($outdatedVersion);
    }
}
