<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class TransmorpherServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(
            'transmorpher', config('transmorpher.transmorpher')
        );
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
