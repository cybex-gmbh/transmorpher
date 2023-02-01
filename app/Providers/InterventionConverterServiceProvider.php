<?php

namespace App\Providers;

use App\Helpers\InterventionTransmorpher\InterventionConverter;
use Illuminate\Support\ServiceProvider;

class InterventionConverterServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(
            'intervention.converter', function () {
            return new InterventionConverter();
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
