<?php

namespace Tests\Unit;

use App\Enums\ResponseState;
use App\Helpers\SodiumHelper;
use App\Jobs\TranscodeVideo;
use App\Models\UploadSlot;
use FilePathHelper;
use Http;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Test;
use Storage;
use Tests\MediaTest;

class VideoTest extends MediaTest
{
    protected const IDENTIFIER = 'testVideo';
    protected const VIDEO_NAME = 'video.mp4';
    protected const CALLBACK_URL = 'http://example.com/callback';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::persistentFake(config('transmorpher.disks.videoDerivatives'));
    }

    #[Test]
    public function ensureVideoUploadSlotCanBeReserved()
    {
        $reserveUploadSlotResponse = $this->json('POST', route('v1.reserveVideoUploadSlot'), [
            'identifier' => self::IDENTIFIER,
            'callback_url' => self::CALLBACK_URL
        ]);

        $reserveUploadSlotResponse->assertOk();

        return $reserveUploadSlotResponse->json()['upload_token'];
    }

    #[Test]
    #[Depends('ensureVideoUploadSlotCanBeReserved')]
    public function ensureTranscodingIsAbortedWhenNewerVersionExists(string $uploadToken)
    {
        Http::fake();

        $uploadSlot = UploadSlot::firstWhere('token', $uploadToken);
        $media = self::$user->Media()->create(['identifier' => self::IDENTIFIER, 'type' => $uploadSlot->media_type]);

        $outdatedVersion = $media->Versions()->create(['number' => 1, 'filename' => sprintf('1-%s', self::VIDEO_NAME)]);
        $media->Versions()->create(['number' => 2, 'filename' => sprintf('2-%s', self::VIDEO_NAME)]);

        TranscodeVideo::dispatch(FilePathHelper::toOriginalFile($outdatedVersion), $outdatedVersion, $uploadSlot);

        $request = Http::recorded()[0][0];
        $transcodingResult = json_decode(SodiumHelper::decrypt($request->data()['signed_response']), true);

        $this->assertEquals(ResponseState::TRANSCODING_ABORTED->getState()->value, $transcodingResult['state']);
        $this->assertEquals(ResponseState::TRANSCODING_ABORTED->getMessage(), $transcodingResult['message']);
    }
}
