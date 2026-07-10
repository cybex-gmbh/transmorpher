<?php

namespace Tests\Unit;

use App\Models\UploadSlot;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UploaderFacadeTest extends TestCase
{
    #[Test]
    public function facadeResolvesUploaderContractAndForwardsCalls(): void
    {
        $this->app->bind('uploader', fn() => new class {
            public function initiateUpload(UploadSlot $uploadSlot): void
            {
            }

            public function getChunkUploadUrl(UploadSlot $uploadSlot, int $chunkNumber): string
            {
                return sprintf('https://example.com/%d', $chunkNumber);
            }

            public function completeUpload(UploadSlot $uploadSlot, array $completionData): void
            {
            }

            public function abortUpload(UploadSlot $uploadSlot): void
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

        Facade::clearResolvedInstance('uploader');

        $uploadSlot = new UploadSlot();
        $uploadSlot->token = 'facade-token';

        $this->assertTrue(\Uploader::needsUploadId());
        $this->assertSame('upload-id', \Uploader::getUploadId($uploadSlot));
        $this->assertSame('https://example.com/5', \Uploader::getChunkUploadUrl($uploadSlot, 5));
        $this->assertSame(['parts' => 'required|array'], \Uploader::getCompletionValidationRules());
    }
}


