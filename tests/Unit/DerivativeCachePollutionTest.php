<?php


use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DerivativeCachePollutionTest extends TestCase
{
    use RefreshDatabase;

    protected const USERNAME = 'TestUser';
    protected const IDENTIFIER = 'test';
    protected const IMAGE_NAME = 'image.jpg';

    /**
     * @test
     */
    public function ensureDerivativeCacheDoesNotGetPolluted()
    {
        Storage::fake(config('transmorpher.disks.originals'));
        Storage::fake(config('transmorpher.disks.imageDerivatives'));

        Sanctum::actingAs(
            $user = User::factory()->create(['name' => self::USERNAME]),
            ['*']
        );

        $reserveUploadSlotResponse = $this->json('POST', route('v1.reserveImageUploadSlot'), [
            'identifier' => self::IDENTIFIER
        ]);
        $reserveUploadSlotResponse->assertOk();

        $uploadResponse = $this->json('POST', route('v1.upload', [$reserveUploadSlotResponse->json()['upload_token']]), [
            'file' => UploadedFile::fake()->image(self::IMAGE_NAME),
            'identifier' => self::IDENTIFIER
        ]);
        $uploadResponse->assertCreated();

        Storage::disk(config('transmorpher.disks.originals'))->assertExists(
            sprintf(
                '%s/1-%s',
                $uploadResponse->json()['public_path'],
                self::IMAGE_NAME
            )
        );

        $media = $user->Media()->whereIdentifier(self::IDENTIFIER)->first();

        $getDerivativeResponse = $this->get(route('getDerivative', [self::USERNAME, $media]));
        $getDerivativeResponse->assertOk();

        // Simulate a version, which is not yet processed. Versions are only set to processed after a successful CDN invalidation.
        $media->Versions()->first()->update(['processed' => 0]);

        $getDerivativeResponse = $this->get(route('getDerivative', [self::USERNAME, $media]));
        $getDerivativeResponse->assertNotFound();
    }
}
