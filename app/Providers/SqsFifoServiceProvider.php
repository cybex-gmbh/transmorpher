<?php

namespace App\Providers;

use App\Classes\FifoQueue\SqsFifoConnector;
use Illuminate\Support\ServiceProvider;

class SqsFifoServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->afterResolving('queue', function ($manager) {
            $manager->addConnector('sqs-fifo', function () {
                return new SqsFifoConnector;
            });
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
