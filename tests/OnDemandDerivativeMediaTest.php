<?php

namespace Tests;

use App\Models\Media;
use App\Models\Version;
use Illuminate\Http\Testing\File;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Test;

abstract class OnDemandDerivativeMediaTest extends MediaTest
{
    protected File $mediaFile;

    protected function uploadMedia(string $uploadToken): TestResponse
    {
        return $this->json('POST', route('v1.upload', [$uploadToken]), [
            'file' => $this->mediaFile,
            'identifier' => $this->identifier
        ]);
    }

    #[Test]
    #[Depends('ensureUploadSlotCanBeReserved')]
    public function ensureMediaCanBeUploaded(string $uploadToken)
    {
        $uploadResponse = $this->uploadMedia($uploadToken);

        $uploadResponse->assertCreated();

        $media = Media::whereIdentifier($this->identifier)->first();
        $version = $media->Versions()->whereNumber($uploadResponse['version'])->first();

        $this->originalsDisk->assertExists($version->originalFilePath());

        return $version;
    }

    #[Test]
    #[Depends('ensureUploadSlotCanBeReserved')]
    #[Depends('ensureMediaCanBeUploaded')]
    public function ensureUploadTokenIsInvalidatedAfterUpload(string $uploadToken)
    {
        $this->uploadMedia($uploadToken)->assertNotFound();
    }

    #[Test]
    #[Depends('ensureMediaCanBeUploaded')]
    public function ensureVersionCanBeSet(Version $version)
    {
        $setVersionResponse = $this->patchJson(route('v1.setVersion', [$version->Media, $version]));
        $setVersionResponse->assertOk();
        $setVersionResponse->assertJsonFragment(['state' => $this->versionSetSuccessful->getState()->value, 'message' => $this->versionSetSuccessful->getMessage()]);

        $setVersion = $version->Media->Versions()->firstWhere('number', $setVersionResponse->json('version'));
        $this->assertModelExists($setVersion);
        $this->assertNotEquals($setVersion, $version);
        $this->assertEquals($setVersion->filename, $version->filename);

        return $setVersion;
    }
}
