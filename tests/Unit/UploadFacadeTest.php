<?php

namespace Tests\Unit;

use App\Models\UploadSlot;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UploadFacadeTest extends TestCase
{
    #[Test]
    public function facadeResolvesUploadContractAndForwardsCalls(): void
    {
        $this->app->bind('upload', fn() => new class {
            public function initiate(UploadSlot $uploadSlot): void
            {
            }

            public function getChunkUploadUrl(UploadSlot $uploadSlot, int $chunkNumber): string
            {
                return sprintf('https://example.com/%d', $chunkNumber);
            }

            public function complete(UploadSlot $uploadSlot, array $completionData): void
            {
            }

            public function abort(UploadSlot $uploadSlot): void
            {
            }

            public function getUploadId(UploadSlot $uploadSlot): ?string
            {
                return 'upload-id';
            }

            public function needsUploadId(): bool
            {
                return true;
            }

            public function getCompletionValidationRules(): array
            {
                return ['parts' => 'required|array'];
            }
        });

        Facade::clearResolvedInstance('upload');

        $uploadSlot = new UploadSlot();
        $uploadSlot->token = 'facade-token';

        $this->assertTrue(\Upload::needsUploadId());
        $this->assertSame('upload-id', \Upload::getUploadId($uploadSlot));
        $this->assertSame('https://example.com/5', \Upload::getChunkUploadUrl($uploadSlot, 5));
        $this->assertSame(['parts' => 'required|array'], \Upload::getCompletionValidationRules());
    }
}



