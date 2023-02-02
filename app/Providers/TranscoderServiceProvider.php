<?php

namespace App\Providers;

use App\Interfaces\TranscoderInterface;
use Illuminate\Support\ServiceProvider;

class TranscoderServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('transcoder', fn(): TranscoderInterface => new (config('transmorpher.transcoder_class')));
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
