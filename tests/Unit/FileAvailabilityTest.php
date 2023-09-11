<?php

namespace Tests\Unit;

use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Storage;
use Tests\TestCase;

class FileAvailabilityTest extends TestCase
{
    protected const IDENTIFIER = 'test';
    protected const IMAGE_NAME = 'image.jpg';

    /**
     * @test
     */
    public function ensureProcessedFilesAreAvailable()
    {
        Storage::fake(config('transmorpher.disks.originals'));
        Storage::fake(config('transmorpher.disks.imageDerivatives'));

        Sanctum::actingAs(
            $user = User::factory()->create(),
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

        $getDerivativeResponse = $this->get(route('getDerivative', [$media->User->name, $media]));
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

        $getDerivativeResponse = $this->get(route('getDerivative', [$media->User->name, $media]));
        $getDerivativeResponse->assertNotFound();
    }
}
