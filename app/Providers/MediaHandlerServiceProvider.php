<?php

namespace App\Providers;

use App\Interfaces\MediaHandlerInterface;
use Illuminate\Support\ServiceProvider;

class MediaHandlerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton('media-handler.document', fn(): MediaHandlerInterface => app()->make(config('transmorpher.media_handlers.document')));
        $this->app->singleton('media-handler.image', fn(): MediaHandlerInterface => app()->make(config('transmorpher.media_handlers.image')));
        $this->app->singleton('media-handler.video', fn(): MediaHandlerInterface => app()->make(config('transmorpher.media_handlers.video')));
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
