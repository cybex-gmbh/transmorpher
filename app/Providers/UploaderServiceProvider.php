<?php

namespace App\Providers;

use App\Interfaces\UploaderContract;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class UploaderServiceProvider extends ServiceProvider implements DeferrableProvider
{
    const string SERVICE_NAME = 'uploader';

    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(static::SERVICE_NAME, fn(): UploaderContract => app()->make(config('transmorpher.uploader')));
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [static::SERVICE_NAME];
    }
}

