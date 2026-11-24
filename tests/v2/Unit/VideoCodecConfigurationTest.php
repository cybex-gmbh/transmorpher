<?php

namespace Tests\v2\Unit;

use App\Classes\Video\Decoder\NvidiaCudaDecoder;
use App\Classes\Video\Encoder\NvidiaH264Encoder;
use Illuminate\Contracts\Container\BindingResolutionException;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\TestCase;
use TypeError;

class VideoCodecConfigurationTest extends TestCase
{
    #[Test]
    public function resolvesConfiguredVideoCodecsFromContainer(): void
    {
        config()->set('transmorpher.media.video.decoder', 'nvidia-cuda');
        config()->set('transmorpher.media.video.encoder', 'nvidia-h264');

        $decoder = app('video.decoder');
        $this->assertInstanceOf(NvidiaCudaDecoder::class, $decoder);
        $this->assertSame('nvidia-cuda', $decoder->name());
        $this->assertSame(['-hwaccel', 'cuda', '-hwaccel_output_format', 'cuda', '-extra_hw_frames', 10], $decoder->inputParameters());

        $encoder = app('video.encoder');
        $this->assertInstanceOf(NvidiaH264Encoder::class, $encoder);
        $this->assertSame('nvidia-h264', $encoder->name());
        $this->assertSame('x264', $encoder->streamingCodec());
        $this->assertSame(['-c:v', 'h264_nvenc'], array_slice($encoder->outputParameters(), 0, 2));
        $this->assertSame(['-c:v', 'h264_nvenc', '-b:v', '6000k'], array_slice($encoder->outputParameters(forMp4Fallback: true), 0, 4));
    }

    #[Test]
    public function failsWhenEncoderDoesNotExist(): void
    {
        config()->set('transmorpher.media.video.encoder', 'does-not-exist');

        $this->expectException(BindingResolutionException::class);

        app('video.encoder');
    }

    #[Test]
    public function failsWhenDecoderDoesNotExist(): void
    {
        config()->set('transmorpher.media.video.decoder', 'does-not-exist');

        $this->expectException(BindingResolutionException::class);

        app('video.decoder');
    }

    #[Test]
    public function failsWhenEncoderDoesNotImplementInterface(): void
    {
        config()->set('transmorpher.media.video.encoder', 'invalid');
        config()->set('transmorpher.interchangeable.video.encoder.invalid', [
            'class' => stdClass::class,
            'parameters' => [],
        ]);

        $this->expectException(TypeError::class);

        app('video.encoder');
    }

    #[Test]
    public function failsWhenDecoderDoesNotImplementInterface(): void
    {
        config()->set('transmorpher.media.video.decoder', 'invalid');
        config()->set('transmorpher.interchangeable.video.decoder.invalid', [
            'class' => stdClass::class,
            'parameters' => [],
        ]);

        $this->expectException(TypeError::class);

        app('video.decoder');
    }
}
