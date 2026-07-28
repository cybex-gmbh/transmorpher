<?php

namespace App\Providers;

use App\Interfaces\TransformInterface;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class TransformServiceProvider extends ServiceProvider implements DeferrableProvider
{
    const string SERVICE_NAME = 'transform';

    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(
            static::SERVICE_NAME,
            fn(): TransformInterface => app()->make(config($this->getTransformerClassPath()))
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

    protected function getTransformerClassPath(): string
    {
        return sprintf('transmorpher.classes.image.transformer.%s.class', config('transmorpher.media.image.transformer'));
    }
}
