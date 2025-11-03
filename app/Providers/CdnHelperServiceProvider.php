<?php

namespace App\Providers;

use App\Interfaces\CdnHelperInterface;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class CdnHelperServiceProvider extends ServiceProvider implements DeferrableProvider
{
    const SERVICE_NAME = 'cdn';

    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(static::SERVICE_NAME, fn(): CdnHelperInterface => app()->make(config('transmorpher.cdn_helper')));
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
