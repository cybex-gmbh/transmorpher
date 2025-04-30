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
    public function register(): void
    {
        $this->app->singleton('cdn', fn(): CdnHelperInterface => app()->make(config('transmorpher.cdn_helper')));
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
