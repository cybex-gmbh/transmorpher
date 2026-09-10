<?php

namespace App\Providers;

use App\Interfaces\CdnHelperInterface;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class CdnHelperServiceProvider extends ServiceProvider implements DeferrableProvider
{
    const string SERVICE_NAME = 'cdn';

    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(
            static::SERVICE_NAME,
            fn(): CdnHelperInterface => app()->make(config($this->getCdnClassPath()))
        );
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

    protected function getCdnClassPath(): string
    {
        return sprintf('transmorpher.classes.cdn.%s.class', config('transmorpher.app.cdn_class'));
    }
}
