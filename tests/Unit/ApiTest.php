<?php

namespace Tests\Unit;

use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Storage;
use Tests\TestCase;

class ApiTest extends TestCase
{
    protected const IDENTIFIER = 'test';
    protected const IMAGE_NAME = 'image.jpg';
    protected static User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::persistentFake(config('transmorpher.disks.originals'));
        Storage::persistentFake(config('transmorpher.disks.imageDerivatives'));

        Sanctum::actingAs(
            self::$user ??= User::factory()->create(),
            ['*']
        );
    }

    /**
     * @test
     */
    public function ensureImageUploadSlotCanBeReserved()
    {
        $reserveUploadSlotResponse = $this->json('POST', route('v1.reserveImageUploadSlot'), [
            'identifier' => self::IDENTIFIER
        ]);
        $reserveUploadSlotResponse->assertOk();

        return $reserveUploadSlotResponse;
    }

    /**
     * @test
     * @depends ensureImageUploadSlotCanBeReserved
     */
    public function ensureImageCanBeUploaded(TestResponse $reserveUploadSlotResponse)
    {
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
    }

    /**
     * @test
     * @depends ensureImageCanBeUploaded
     */
    public function ensureProcessedFilesAreAvailable()
    {
        $media = self::$user->Media()->whereIdentifier(self::IDENTIFIER)->first();

        $getDerivativeResponse = $this->get(route('getDerivative', [self::$user->name, $media]));
        $getDerivativeResponse->assertOk();

        return $media;
    }

    /**
     * @test
     * @depends ensureProcessedFilesAreAvailable
     */
    public function ensureUnprocessedFilesAreNotAvailable(Media $media)
    {
        $media->Versions()->first()->update(['processed' => 0]);

        $getDerivativeResponse = $this->get(route('getDerivative', [self::$user->name, $media]));
        $getDerivativeResponse->assertNotFound();
    }
}
