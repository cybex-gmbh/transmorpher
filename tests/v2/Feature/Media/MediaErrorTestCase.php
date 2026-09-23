<?php

namespace Tests\v2\Feature\Media;

use App\Classes\UploadHandler\DefaultUploadHandler;
use App\Models\Media;
use App\Models\UploadSlot;
use App\Models\User;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\v2\Support\MediaTestBase;

abstract class MediaErrorTestCase extends MediaTestBase
{
    protected string $invalidMimeFileFilePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withExceptionHandling();
    }

    #[Test]
    #[DataProvider('invalidIdentifierProvider')]
    public function cannotReserveUploadSlotWithInvalidIdentifier(string $identifier): void
    {
        $response = $this->reserveUploadSlot($identifier);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['identifier']);
    }

    #[Test]
    #[DataProvider('missingFilenameProvider')]
    public function cannotReserveUploadSlotWithoutFilename(?string $filename): void
    {
        $payload = [
            'identifier' => $this->identifier,
        ];

        if ($filename !== null) {
            $payload['filename'] = $filename;
        }

        $response = $this->postJson($this->reserveUploadSlotRoute($this->mediaType), $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['filename']);
    }

    #[Test]
    public function cannotUploadWithoutFile(): void
    {
        $uploadSlot = $this->reserveValidUploadSlot();

        $response = $this->upload($uploadSlot, [
            'identifier' => $uploadSlot->identifier,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['file']);
    }

    #[Test]
    public function cannotUploadWithExpiredSlot(): void
    {
        $uploadSlot = $this->reserveValidUploadSlot();
        $uploadSlot->invalidate();

        $response = $this->upload($uploadSlot, [
            'identifier' => $uploadSlot->identifier,
            'file' => $this->fakeFile(),
        ]);

        $response->assertNotFound();
        $response->assertJsonStructure(['message']);
    }

    #[Test]
    #[DataProvider('nonMatchingIdentifierProvider')]
    public function cannotUploadWithNonMatchingIdentifier(string $identifier): void
    {
        $uploadSlot = $this->reserveValidUploadSlot();

        $response = $this->upload($uploadSlot, [
            'identifier' => $identifier,
            'file' => $this->fakeFile(),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['identifier']);
    }

    #[Test]
    public function cannotUploadWithMissingIdentifier(): void
    {
        $uploadSlot = $this->reserveValidUploadSlot();

        $response = $this->upload($uploadSlot, [
            'file' => $this->fakeFile(),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['identifier']);
    }

    #[Test]
    #[DataProvider('invalidIdentifierProvider')]
    public function cannotUploadWithInvalidIdentifier(string $identifier): void
    {
        $uploadSlot = $this->reserveValidUploadSlot();

        $response = $this->upload($uploadSlot, [
            'identifier' => $identifier,
            'file' => $this->fakeFile(),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['identifier']);
    }

    #[Test]
    public function cannotDeleteNonExistentMedia(): void
    {
        $response = $this->deleteJson($this->deleteMediaRoute('non-existent-identifier'));

        $response->assertNotFound();
        $response->assertJsonStructure(['message']);
    }

    #[Test]
    public function cannotDeleteMediaOfAnotherUser(): void
    {
        $version = $this->performUpload();
        $media = $version->Media;

        Sanctum::actingAs(User::factory()->create(), ['*']);

        $response = $this->deleteJson($this->deleteMediaRoute($media->identifier));

        $response->assertNotFound();
        $response->assertJsonStructure(['message']);
    }

    #[Test]
    public function cannotSetInvalidVersion(): void
    {
        $version = $this->performUpload();

        $response = $this->patchJson($this->setVersionRoute($version->Media->identifier, 999999));

        $response->assertNotFound();
        $response->assertJsonStructure(['message']);
    }

    #[Test]
    public function cannotCompleteUploadWithInvalidMimeType(): void
    {
        $uploadSlot = $this->reserveValidUploadSlot();

        $this->sendFile($uploadSlot, $this->fakeFile($this->invalidMimeFileFilePath))
            ->assertOk();

        $response = $this->completeUpload($uploadSlot);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['file']);
        $this->assertNull(Media::firstWhere('identifier', $uploadSlot->identifier));
    }

    #[Test]
    public function leavesNoRemnantsWhenUploadCompletionFails(): void
    {
        $uploadSlot = $this->reserveValidUploadSlot();

        $this->sendFile($uploadSlot, $this->fakeFile($this->invalidMimeFileFilePath))
            ->assertOk();

        $chunkDisk = Storage::disk(config('chunk-upload.storage.disk'));
        $temporaryPath = implode(DIRECTORY_SEPARATOR, [
            config('chunk-upload.storage.chunks'),
            DefaultUploadHandler::createTempFilename($uploadSlot),
        ]);

        $chunkDisk->assertExists($temporaryPath);

        $completeResponse = $this->completeUpload($uploadSlot);
        $completeResponse->assertUnprocessable();
        $completeResponse->assertJsonValidationErrors(['file']);

        $uploadSlot->refresh();
        $this->assertFalse($uploadSlot->is_valid);

        $this->assertNull(Media::firstWhere('identifier', $uploadSlot->identifier));
        $this->originalsDisk->assertMissing($uploadSlot->originalFilePath);
        $chunkDisk->assertMissing($temporaryPath);
    }

    public static function invalidIdentifierProvider(): array
    {
        return [
            'leading hyphen' => ['-invalid'],
            'invalid character' => ['not!valid'],
        ];
    }

    public static function missingFilenameProvider(): array
    {
        return [
            'missing filename key' => [null],
            'empty filename string' => [''],
            'filename only spaces' => ['   '],
            'invalid filename' => ['file/.invalid'],
        ];
    }

    public static function nonMatchingIdentifierProvider(): array
    {
        return [
            'different identifier' => ['another-identifier'],
            'different identifier with same prefix' => ['test-image-other'],
        ];
    }

    protected function reserveValidUploadSlot(): UploadSlot
    {
        $reserveResponse = $this->reserveUploadSlot();
        $reserveResponse->assertOk();

        $uploadSlot = UploadSlot::withoutGlobalScopes()->firstWhere('token', $reserveResponse->json('upload_token'));
        $this->assertModelExists($uploadSlot);

        return $uploadSlot;
    }

    /**
     * @param array<string, string|File> $data
     */
    protected function upload(UploadSlot $uploadSlot, array $data): TestResponse
    {
        $files = array_filter($data, fn(mixed $value): bool => $value instanceof File);
        $parameters = array_filter($data, fn(mixed $value): bool => !$value instanceof File);

        return $this->call('PUT', $this->uploadRoute($uploadSlot->token), $parameters, [], $files, [
            'HTTP_ACCEPT' => 'application/json',
        ]);
    }
}
