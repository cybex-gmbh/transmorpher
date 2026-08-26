<?php

namespace App\Providers;

use App\Interfaces\UploadHandlerInterface;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class UploadHandlerServiceProvider extends ServiceProvider implements DeferrableProvider
{
    const string SERVICE_NAME = 'upload-handler';

    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(static::SERVICE_NAME, function (): UploadHandlerInterface {
            $uploadHandler = config(sprintf('transmorpher.handler.upload.%s.class', config('transmorpher.upload_handler')));
            $uploadHandler::ensurePrerequisitesMet();

            return app()->make($uploadHandler);
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
