<?php

namespace Tests\v2\Unit;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Enums\Transformation;
use App\Models\Media;
use App\Models\UploadSlot;
use App\Models\User;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModelEventsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (MediaStorage::cases() as $mediaStorage) {
            Storage::fake($mediaStorage->getDiskName());
        }

        $this->user = User::factory()->create();
    }

    #[Test]
    #[DataProvider('provideOnDemandMediaTypes')]
    public function deletingVersionRemovesOriginalAndOnDemandDerivativesForOnDemandMedia(MediaType $type, MediaStorage $derivativesStorage, string $filename): void
    {
        $media = $this->createMedia(type: $type, identifier: sprintf('%s-on-demand-delete', $type->value));
        $version = $this->createVersion($media, 1, $filename);

        MediaStorage::ORIGINALS->getDisk()->put($version->originalFilePath(), 'original');
        $derivativesStorage->getDisk()->put(
            $version->onDemandDerivativeFilePath(),
            'derivative',
        );

        $version->delete();

        $this->assertModelMissing($version);
        MediaStorage::ORIGINALS->getDisk()->assertMissing($version->originalFilePath());
        $derivativesStorage->getDisk()->assertMissing($version->onDemandDerivativeDirectoryPath());
    }

    #[Test]
    public function deletingVersionKeepsOriginalFileWhenAnotherVersionStillReferencesIt(): void
    {
        $media = $this->createMedia(identifier: 'shared-original');
        $firstVersion = $this->createVersion($media, 1, 'shared.jpg');
        $secondVersion = $this->createVersion($media, 2, 'shared.jpg');

        MediaStorage::ORIGINALS->getDisk()->put($firstVersion->originalFilePath(), 'original');

        $firstVersion->delete();

        $this->assertModelMissing($firstVersion);
        $this->assertModelExists($secondVersion);
        MediaStorage::ORIGINALS->getDisk()->assertExists($firstVersion->originalFilePath());
        MediaStorage::ORIGINALS->getDisk()->assertExists($secondVersion->originalFilePath());
    }

    #[Test]
    public function deletingVersionDoesNotDeleteVideoDerivatives(): void
    {
        $media = $this->createMedia(type: MediaType::VIDEO, identifier: 'video-version-delete');
        $version = $this->createVersion($media, 1, 'test-video.mp4');

        MediaStorage::ORIGINALS->getDisk()->put($version->originalFilePath(), 'original');
        MediaStorage::VIDEO_DERIVATIVES->getDisk()->put($media->videoDerivativeFilePath('mp4', 'video.mp4'), 'video');

        $version->delete();

        $this->assertModelMissing($version);
        MediaStorage::ORIGINALS->getDisk()->assertMissing($version->originalFilePath());
        MediaStorage::VIDEO_DERIVATIVES->getDisk()->assertExists($media->videoDerivativeFilePath('mp4', 'video.mp4'));
    }

    #[Test]
    public function deletingMediaDeletesRelatedModelsAndBaseDirectories(): void
    {
        $media = $this->createMedia(identifier: 'media-delete');
        $version = $this->createVersion($media, 1, 'media-delete.jpg');
        $uploadSlot = $this->createUploadSlot($media->identifier, $media->type, 'media-delete.jpg');

        MediaStorage::ORIGINALS->getDisk()->put($version->originalFilePath(), 'original');
        MediaStorage::IMAGE_DERIVATIVES->getDisk()->put(
            $version->onDemandDerivativeFilePath([
                Transformation::WIDTH->value => 320,
            ]),
            'derivative',
        );

        $media->delete();

        $this->assertModelMissing($media);
        $this->assertModelMissing($version);
        $this->assertModelMissing($uploadSlot);
        MediaStorage::ORIGINALS->getDisk()->assertMissing($media->baseDirectory());
        MediaStorage::IMAGE_DERIVATIVES->getDisk()->assertMissing($media->baseDirectory());
    }

    #[Test]
    public function deletingUserDeletesRelatedMediaAndAllMediaDirectories(): void
    {
        $media = $this->createMedia(identifier: 'user-delete');
        $version = $this->createVersion($media, 1, 'user-delete.jpg');
        $uploadSlot = $this->createUploadSlot($media->identifier, $media->type, 'user-delete.jpg');

        foreach (MediaStorage::cases() as $mediaStorage) {
            $mediaStorage->getDisk()->put(sprintf('%s/marker.txt', $this->user->name), 'marker');
        }

        MediaStorage::ORIGINALS->getDisk()->put($version->originalFilePath(), 'original');

        $this->user->delete();

        $this->assertModelMissing($this->user);
        $this->assertModelMissing($media);
        $this->assertModelMissing($version);
        $this->assertModelMissing($uploadSlot);

        foreach (MediaStorage::cases() as $mediaStorage) {
            $mediaStorage->getDisk()->assertMissing(sprintf('%s/marker.txt', $this->user->name));
        }

        MediaStorage::ORIGINALS->getDisk()->assertMissing($version->originalFilePath());
    }

    protected function createMedia(MediaType $type = MediaType::IMAGE, string $identifier = 'model-events'): Media
    {
        return $this->user->Media()->create([
            'identifier' => $identifier,
            'type' => $type,
        ]);
    }

    protected function createVersion(Media $media, int $number, string $filename): Version
    {
        return $media->Versions()->create([
            'number' => $number,
            'filename' => $filename,
        ]);
    }

    protected function createUploadSlot(string $identifier, MediaType $type, string $filename): UploadSlot
    {
        return $this->user->UploadSlots()->create([
            'identifier' => $identifier,
            'filename' => $filename,
            'media_type' => $type,
        ]);
    }

    public static function provideOnDemandMediaTypes(): array
    {
        return [
            'image' => [MediaType::IMAGE, MediaStorage::IMAGE_DERIVATIVES, 'test-image.jpg'],
            'document' => [MediaType::DOCUMENT, MediaStorage::DOCUMENT_DERIVATIVES, 'test-document.pdf'],
        ];
    }
}
