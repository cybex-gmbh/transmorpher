<?php

namespace App\Providers;

use App\Interfaces\TransmorpherInterface;
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
        $this->app->singleton('transmorpher', fn(): TransmorpherInterface => new (config('transmorpher.transmorpher_class')));
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
