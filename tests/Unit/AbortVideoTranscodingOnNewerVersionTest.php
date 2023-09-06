<?php


use App\Enums\ResponseState;
use App\Helpers\SigningHelper;
use App\Jobs\TranscodeVideo;
use App\Models\UploadSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AbortVideoTranscodingOnNewerVersionTest extends TestCase
{
    use RefreshDatabase;

    protected const USERNAME = 'TestUser';
    protected const IDENTIFIER = 'test';
    protected const VIDEO_NAME = 'video.mp4';
    protected const CALLBACK_URL = 'http://example.com/callback';

    /**
     * @test
     */
    public function ensureTranscodingIsAbortedWhenNewerVersionExists()
    {
        Sanctum::actingAs(
            $user = User::factory()->create(['name' => self::USERNAME]),
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
        $transcodingResult = json_decode(SigningHelper::decrypt($request->data()['signed_response']), true);

        $this->assertEquals(ResponseState::TRANSCODING_ABORTED->getState()->value, $transcodingResult['state']);
        $this->assertEquals(ResponseState::TRANSCODING_ABORTED->getMessage(), $transcodingResult['message']);
    }
}
