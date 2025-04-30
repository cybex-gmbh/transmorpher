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
    public function register(): void
    {
        $this->app->singleton('transform', fn(): TransformInterface => app()->make(config('transmorpher.transform_class')));
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
