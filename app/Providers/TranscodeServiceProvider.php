<?php

namespace App\Providers;

use App\Interfaces\TranscodeInterface;
use Illuminate\Support\ServiceProvider;

class TranscodeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('transcode', fn(): TranscodeInterface => new (config('transmorpher.transcode_class')));
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
