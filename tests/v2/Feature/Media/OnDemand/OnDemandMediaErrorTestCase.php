<?php

namespace Tests\v2\Feature\Media\OnDemand;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\v2\Feature\Media\MediaErrorTestCase;
use Tests\v2\Support\IsOnDemandMedia;

abstract class OnDemandMediaErrorTestCase extends MediaErrorTestCase
{
    use IsOnDemandMedia;

    #[Test]
    #[DataProvider('invalidFormatProvider')]
    public function cannotDownloadOnDemandDerivativeWithInvalidFormat(string $transformations): void
    {
        $version = $this->performUpload();

        $response = $this->getJson(route(
            sprintf('get%sDerivative', ucfirst($this->mediaType->value)),
            [$this->user, $version->Media, $transformations]
        ));

        $response->assertStatus(400);
        $response->assertJsonStructure(['message']);
    }

    #[Test]
    #[DataProvider('invalidTransformationProvider')]
    public function cannotDownloadOnDemandDerivativeWithInvalidTransformations(string $transformations): void
    {
        $version = $this->performUpload();

        $response = $this->getJson(route(
            sprintf('get%sDerivative', ucfirst($this->mediaType->value)),
            [$this->user, $version->Media, $transformations]
        ));

        $response->assertStatus(400);
        $response->assertJsonStructure(['message']);
    }

    #[Test]
    #[DataProvider('invalidFormatProvider')]
    public function cannotDownloadSpecificVersionDerivativeWithInvalidFormat(string $transformations): void
    {
        $version = $this->performUpload();

        $response = $this->getJson(route(
            sprintf('v2.get%sDerivativeForVersion', ucfirst($this->mediaType->value)),
            [$version->Media, $version, $transformations]
        ));

        $response->assertStatus(400);
        $response->assertJsonStructure(['message']);
    }

    #[Test]
    #[DataProvider('invalidTransformationProvider')]
    public function cannotDownloadSpecificVersionDerivativeWithInvalidTransformations(string $transformations): void
    {
        $version = $this->performUpload();

        $response = $this->getJson(route(
            sprintf('v2.get%sDerivativeForVersion', ucfirst($this->mediaType->value)),
            [$version->Media, $version, $transformations]
        ));

        $response->assertStatus(400);
        $response->assertJsonStructure(['message']);
    }

    #[Test]
    public function cannotDownloadSpecificVersionDerivativeWithInvalidVersion(): void
    {
        $version = $this->performUpload();

        $response = $this->getJson(route(sprintf('v2.get%sDerivativeForVersion', ucfirst($this->mediaType->value)), [
            $version->Media,
            999999,
            'q-1',
        ]));

        $response->assertNotFound();
        $response->assertJsonStructure(['message']);
    }

    #[Test]
    public function cannotDownloadVersionOriginalWithInvalidVersion(): void
    {
        $version = $this->performUpload();

        $response = $this->getJson(route(sprintf('v2.get%sOriginal', ucfirst($this->mediaType->value)), [
            $version->Media,
            999999,
        ]));

        $response->assertNotFound();
        $response->assertJsonStructure(['message']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidFormatProvider(): array
    {
        return [
            'unsupported format' => ['f-aaa'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidTransformationProvider(): array
    {
        return [
            'missing transformation separator' => ['w'],
        ];
    }
}


