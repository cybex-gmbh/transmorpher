<?php

namespace App\Providers;

use App\Helpers\FilePathHelper;
use Illuminate\Support\ServiceProvider;

class FilePathHelperServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(
            'file.path', function () {
            return new FilePathHelper();
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
