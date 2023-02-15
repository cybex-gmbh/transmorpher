<?php

namespace App\Providers;

use App\Interfaces\CloudStorageInterface;
use Illuminate\Support\ServiceProvider;

class CloudStorageServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('cloud.storage', fn(): CloudStorageInterface => new (config('transmorpher.cloud_storage_helper')));
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
