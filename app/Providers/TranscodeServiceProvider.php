<?php

namespace App\Providers;

use App\Interfaces\TranscodeInterface;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class TranscodeServiceProvider extends ServiceProvider implements DeferrableProvider
{
    const string SERVICE_NAME = 'transcode';

    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(
            static::SERVICE_NAME,
            fn(): TranscodeInterface => app()->make(config($this->getTranscoderClassPath()))
        );
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [static::SERVICE_NAME];
    }

    protected function getTranscoderClassPath(): string
    {
        return sprintf('transmorpher.classes.video.transcoder.%s.class', config('transmorpher.media.video.transcoder'));
    }
}
