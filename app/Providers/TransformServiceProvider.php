<?php

namespace App\Providers;

use App\Interfaces\TransformInterface;
use Illuminate\Support\ServiceProvider;

class TransformServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('transform', fn(): TransformInterface => new (config('transmorpher.transform_class')));
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
