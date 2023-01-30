<?php

namespace App\Providers;

use App\Helpers\InterventionTransmorpher\InterventionTransmorpher;
use Illuminate\Support\ServiceProvider;

class InterventionTransmorpherServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(
            'InterventionTransmorpher', function () {
            return new InterventionTransmorpher();
        });
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
