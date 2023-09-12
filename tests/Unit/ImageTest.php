<?php

namespace Tests\Unit;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Storage;

class ImageTest extends MediaTest
{
    protected const IDENTIFIER = 'testImage';
    protected const IMAGE_NAME = 'image.jpg';


    protected function setUp(): void
    {
        parent::setUp();

        Storage::persistentFake(config('transmorpher.disks.imageDerivatives'));
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

        return $reserveUploadSlotResponse->json()['upload_token'];
    }

    /**
     * @test
     * @depends ensureImageUploadSlotCanBeReserved
     */
    public function ensureImageCanBeUploaded(string $uploadToken)
    {
        $uploadResponse = $this->json('POST', route('v1.upload', [$uploadToken]), [
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
