<?php

namespace Tests\Unit;

use App\Enums\MediaType;
use App\Models\Media;
use App\Models\UploadSlot;
use App\Models\User;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class V2UploadFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('cache.default', 'array');
    }

    protected function useUploaderForTest(object $uploader): void
    {
        // Ensure deferred upload provider is loaded before overriding the service.
        app('upload');
        $this->app->instance('upload', $uploader);
        Facade::clearResolvedInstance('upload');
    }

    #[Test]
    public function reserveUploadSlotStoresFilenameAndReturnsUploadToken(): void
    {
        $user = User::first() ?: User::factory()->create();
        $identifier = 'featureUploadSlotImage-' . uniqid();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('v2.reserveUploadSlot', MediaType::IMAGE), [
            'identifier' => $identifier,
            'filename' => 'sample-image.jpg',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['upload_token']);

        $uploadSlot = UploadSlot::withoutGlobalScopes()->firstWhere('identifier', $identifier);
        $this->assertNotNull($uploadSlot);
        $this->assertSame('sample-image.jpg', $uploadSlot->filename);
    }

    #[Test]
    public function getChunkUploadUrlReturnsValueFromUploader(): void
    {
        $user = User::first() ?: User::factory()->create();
        $identifier = 'featureChunkUrlImage-' . uniqid();
        $uploadSlot = $user->UploadSlots()->withoutGlobalScopes()->create([
            'identifier' => $identifier,
            'media_type' => MediaType::IMAGE,
        ]);

        $this->useUploaderForTest(new class {
            public function initiateUpload(UploadSlot $uploadSlot): void
            {
            }

            public function getChunkUploadUrl(UploadSlot $uploadSlot, int $chunkNumber): string
            {
                return sprintf('https://example.com/chunks/%d', $chunkNumber);
            }

            public function completeUpload(UploadSlot $uploadSlot, array $completionData): void
            {
            }

            public function abortUpload(UploadSlot $uploadSlot): void
            {
            }

            public function getUploadId(UploadSlot $uploadSlot): ?string
            {
                return null;
            }

            public function needsUploadId(): bool
            {
                return false;
            }

            public function getCompletionValidationRules(): array
            {
                return [];
            }
        });

        $response = $this->getJson(route('v2.chunkUrl', [$uploadSlot, 3]));

        $response->assertOk();
        $response->assertJsonFragment(['url' => 'https://example.com/chunks/3']);
    }

    #[Test]
    public function completeUploadValidationFailureDoesNotCreateMediaRecords(): void
    {
        $user = User::first() ?: User::factory()->create();
        $identifier = 'featureRollbackImage-' . uniqid();

        $uploadSlot = $user->UploadSlots()->withoutGlobalScopes()->create([
            'identifier' => $identifier,
            'media_type' => MediaType::IMAGE,
        ]);

        $this->useUploaderForTest(new class {
            public function initiateUpload(UploadSlot $uploadSlot): void
            {
            }

            public function getChunkUploadUrl(UploadSlot $uploadSlot, int $chunkNumber): string
            {
                return 'https://example.com/chunk-url';
            }

            public function completeUpload(UploadSlot $uploadSlot, array $completionData): void
            {
                throw ValidationException::withMessages(['file' => ['Invalid mime type.']]);
            }

            public function abortUpload(UploadSlot $uploadSlot): void
            {
            }

            public function getUploadId(UploadSlot $uploadSlot): ?string
            {
                return null;
            }

            public function needsUploadId(): bool
            {
                return false;
            }

            public function getCompletionValidationRules(): array
            {
                return [];
            }
        });

        $response = $this->postJson(route('v2.completeUpload', $uploadSlot), []);

        $response->assertStatus(422);
        $this->assertNull(Media::query()->where('identifier', $identifier)->first());
        $this->assertFalse($uploadSlot->fresh()->is_valid);
    }
}



