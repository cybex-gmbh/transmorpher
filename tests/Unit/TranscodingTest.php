<?php

namespace Tests\Unit;

use App\Enums\ResponseState;
use App\Helpers\SodiumHelper;
use App\Jobs\TranscodeVideo;
use App\Models\UploadSlot;
use App\Models\User;
use FilePathHelper;
use Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Storage;
use Tests\TestCase;

class TranscodingTest extends TestCase
{
    use RefreshDatabase;

    protected const IDENTIFIER = 'test';
    protected const VIDEO_NAME = 'video.mp4';
    protected const CALLBACK_URL = 'http://example.com/callback';

    /**
     * @test
     */
    public function ensureTranscodingIsAbortedWhenNewerVersionExists()
    {
        Sanctum::actingAs(
            $user = User::factory()->create(),
            ['*']
        );

        Storage::fake(config('transmorpher.disks.originals'));
        Storage::fake(config('transmorpher.disks.videoDerivatives'));

        Http::fake();

        $reserveUploadSlotResponse = $this->json('POST', route('v1.reserveVideoUploadSlot'), [
            'identifier' => self::IDENTIFIER,
            'callback_url' => self::CALLBACK_URL
        ]);
        $reserveUploadSlotResponse->assertOk();

        $uploadSlot = UploadSlot::first();
        $media = $user->Media()->create(['identifier' => self::IDENTIFIER, 'type' => $uploadSlot->media_type]);

        $outdatedVersion = $media->Versions()->create(['number' => 1, 'filename' => sprintf('1-%s', self::VIDEO_NAME)]);
        $media->Versions()->create(['number' => 2, 'filename' => sprintf('2-%s', self::VIDEO_NAME)]);

        TranscodeVideo::dispatch(FilePathHelper::toOriginalFile($media, 1), $media, $outdatedVersion, $uploadSlot);

        $request = Http::recorded()[0][0];
        $transcodingResult = json_decode(SodiumHelper::decrypt($request->data()['signed_response']), true);

        $this->assertEquals(ResponseState::TRANSCODING_ABORTED->getState()->value, $transcodingResult['state']);
        $this->assertEquals(ResponseState::TRANSCODING_ABORTED->getMessage(), $transcodingResult['message']);
    }
}
