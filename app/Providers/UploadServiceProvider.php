<?php

namespace App\Providers;

use App\Interfaces\UploadContract;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class UploadServiceProvider extends ServiceProvider implements DeferrableProvider
{
    const string SERVICE_NAME = 'upload';

    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(static::SERVICE_NAME, function (): UploadContract {
            $uploadClass = config(sprintf('transmorpher.handler.upload.%s.class', config('transmorpher.upload_handler')));
            $uploadClass::ensurePrerequisitesMet();

            return app()->make($uploadClass);
        });
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


