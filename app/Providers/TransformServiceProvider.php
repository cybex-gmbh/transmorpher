<?php

namespace App\Providers;

use App\Interfaces\TransformInterface;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class TransformServiceProvider extends ServiceProvider implements DeferrableProvider
{
    const SERVICE_NAME = 'transform';

    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(static::SERVICE_NAME, fn(): TransformInterface => app()->make(config('transmorpher.transform_class')));
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
