<?php

namespace App\Providers;

use App\Interfaces\MediaHandlerInterface;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class MediaHandlerServiceProvider extends ServiceProvider implements DeferrableProvider
{
    const string DOCUMENT_SERVICE_NAME = 'media-handler.document';
    const string IMAGE_SERVICE_NAME = 'media-handler.image';
    const string VIDEO_SERVICE_NAME = 'media-handler.video';

    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(
            static::DOCUMENT_SERVICE_NAME,
            fn(): MediaHandlerInterface => app()->make(config($this->getMediaHandlerClassPath('document')))
        );
        $this->app->singleton(
            static::IMAGE_SERVICE_NAME,
            fn(): MediaHandlerInterface => app()->make(config($this->getMediaHandlerClassPath('image')))
        );
        $this->app->singleton(
            static::VIDEO_SERVICE_NAME,
            fn(): MediaHandlerInterface => app()->make(config($this->getMediaHandlerClassPath('video')))
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
            static::DOCUMENT_SERVICE_NAME,
            static::IMAGE_SERVICE_NAME,
            static::VIDEO_SERVICE_NAME,
        ];
    }

    protected function getMediaHandlerClassPath(string $mediaType): string
    {
        return sprintf(
            'transmorpher.classes.handler.media.%s.%s.class',
            $mediaType,
            config(sprintf('transmorpher.media.%s.handler', $mediaType))
        );
    }
}
