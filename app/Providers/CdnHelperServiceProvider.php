<?php

namespace App\Providers;

use App\Interfaces\CdnHelperInterface;
use Illuminate\Support\ServiceProvider;

class CdnHelperServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('cdn', fn(): CdnHelperInterface => new (config('transmorpher.cdn_helper')));
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
