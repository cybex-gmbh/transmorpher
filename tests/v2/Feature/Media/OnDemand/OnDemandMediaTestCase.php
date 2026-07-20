<?php

namespace Tests\v2\Feature\Media\OnDemand;

use App\Console\Commands\PurgeDerivatives;
use App\Enums\ClientNotification;
use App\Helpers\SodiumHelper;
use Artisan;
use Http;
use Illuminate\Http\Client\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\v2\Feature\Media\MediaTestCase;
use Tests\v2\Support\IsOnDemandMedia;

abstract class OnDemandMediaTestCase extends MediaTestCase
{
    use IsOnDemandMedia;

    protected string $originalContentType;
    protected string $derivativeContentType;

    #[Test]
    public function canUploadMedia(): void
    {
        $version = $this->performUpload();

        $this->assertModelExists($version);
        $this->assertModelExists($version->Media);
        $this->assertSame($this->mediaType, $version->Media->type);
        $this->assertSame(1, $version->number);
        $this->assertTrue((bool)$version->processed);
        $this->originalsDisk->assertExists($version->originalFilePath());
    }

    #[Test]
    public function canSetVersion(): void
    {
        $originalVersion = $this->performUpload();

        $response = $this->setVersion($originalVersion->Media, $originalVersion);

        $response->assertOk();
        $response->assertJsonFragment([
            'state' => $this->versionSetSuccessfulState->getState()->value,
            'message' => $this->versionSetSuccessfulState->getMessage(),
        ]);

        $newVersion = $originalVersion->Media->Versions()->whereNumber($response->json('version'))->first();

        $this->assertModelExists($newVersion);
        $this->assertNotEquals($originalVersion->id, $newVersion->id);
        $this->assertSame($originalVersion->filename, $newVersion->filename);
        $this->assertTrue((bool)$newVersion->processed);
    }

    #[Test]
    public function canDownloadOriginal(): void
    {
        $version = $this->performUpload();

        $this->getOriginal($version)
            ->assertOk()
            ->assertHeader('Content-Type', $this->originalContentType);
    }

    #[Test]
    public function canDownloadSpecificVersionDerivative(): void
    {
        $version = $this->performUpload();

        $response = $this->getDerivativeForVersion($version);

        $response->assertOk();
        $response->assertHeader('Content-Type', $this->derivativeContentType);
        $this->derivativesDisk->assertExists($version->onDemandDerivativeFilePath());
    }

    #[Test]
    public function canDownloadDerivative(): void
    {
        $version = $this->performUpload();

        $response = $this->getPublicDerivative($version);

        $response->assertOk();
        $response->assertHeader('Content-Type', $this->derivativeContentType);
    }

    #[Test]
    public function canPurgeDerivatives(): void
    {
        $version = $this->performUpload();

        $this->getDerivativeForVersion($version)->assertOk();
        $this->derivativesDisk->assertExists($version->onDemandDerivativeFilePath());

        $counterPath = (string)config('transmorpher.cache_invalidation_counter_file_path');
        $counterBefore = (int)($this->originalsDisk->get($counterPath) ?? 0);

        Http::fake([
            $this->user->api_url => Http::response(),
        ]);

        $purgeFilter = '--' . $this->mediaType->value;
        Artisan::call(PurgeDerivatives::class, [$purgeFilter => true]);

        $counterAfter = (int)$this->originalsDisk->get($counterPath);

        Http::assertSent(function (Request $request) use ($counterAfter) {
            $decryptedNotification = json_decode(SodiumHelper::decrypt($request['signed_notification']), true);

            return $request->url() === $this->user->api_url
                && $decryptedNotification['notification_type'] === ClientNotification::CACHE_INVALIDATION->value
                && $decryptedNotification['cache_invalidator'] === $counterAfter;
        });

        $this->assertSame($counterBefore + 1, $counterAfter);
        $this->derivativesDisk->assertMissing($version->onDemandDerivativeFilePath());
    }
}
