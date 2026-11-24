<?php

namespace App\Providers;

use App\Interfaces\VideoDecoderInterface;
use App\Interfaces\VideoEncoderInterface;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class VideoCodecServiceProvider extends ServiceProvider implements DeferrableProvider
{
    const string DECODER_SERVICE_NAME = 'video.decoder';
    const string ENCODER_SERVICE_NAME = 'video.encoder';

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(
            static::DECODER_SERVICE_NAME,
            fn(): VideoDecoderInterface => app()->make(config($this->getDecoderClassPath()))
        );

        $this->app->singleton(
            static::ENCODER_SERVICE_NAME,
            fn(): VideoEncoderInterface => app()->make(config($this->getEncoderClassPath()))
        );
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            static::DECODER_SERVICE_NAME,
            static::ENCODER_SERVICE_NAME,
        ];
    }

    protected function getDecoderClassPath(): string
    {
        return sprintf('transmorpher.interchangeable.video.decoder.%s.class', config('transmorpher.media.video.decoder'));
    }

    protected function getEncoderClassPath(): string
    {
        return sprintf('transmorpher.interchangeable.video.encoder.%s.class', config('transmorpher.media.video.encoder'));
    }
}
