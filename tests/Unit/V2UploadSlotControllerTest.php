<?php

namespace Tests\Unit;

use App\Enums\MediaType;
use App\Http\Controllers\V2\UploadController;
use App\Http\Requests\V2\CompleteUploadRequest;
use App\Models\Media;
use App\Models\UploadSlot;
use App\Models\User;
use Illuminate\Support\Facades\Facade;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class V2UploadSlotControllerTest extends TestCase
{
    #[Test]
    public function failedMimeValidationRollsBackMediaAndVersionCreation(): void
    {
        $user = User::factory()->create();
        $identifier = 'rollback-test-' . uniqid();

        $uploadSlot = $user->UploadSlots()->withoutGlobalScopes()->create([
            'identifier' => $identifier,
            'media_type' => MediaType::IMAGE,
            'validation_rules' => null,
        ]);

        $this->app->bind('upload', fn() => new class {
            public function initiate(UploadSlot $uploadSlot): void
            {
            }

            public function getChunkUploadUrl(UploadSlot $uploadSlot, int $chunkNumber): string
            {
                return 'https://example.com/chunk';
            }

            public function complete(CompleteUploadRequest $request, UploadSlot $uploadSlot): void
            {
                throw ValidationException::withMessages(['file' => ['Invalid mime type.']]);
            }

            public function abort(UploadSlot $uploadSlot): void
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

        Facade::clearResolvedInstance('upload');

        $controller = app(UploadController::class);
        $reflection = new ReflectionClass($controller);
        $saveFileMethod = $reflection->getMethod('completeFileOperations');
        $saveFileMethod->setAccessible(true);

        try {
            $request = Mockery::mock(CompleteUploadRequest::class)->makePartial();
            $saveFileMethod->invoke($controller, $request, $uploadSlot);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException) {
            $this->assertNull(Media::query()->where('identifier', $identifier)->first());
        }
    }
}
