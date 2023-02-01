<?php

namespace App\Providers;

use App\Exceptions\InvalidTransmorpherException;
use App\Interfaces\TransmorpherInterface;
use Illuminate\Support\ServiceProvider;

class TransmorpherServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     * @throws InvalidTransmorpherException
     */
    public function register()
    {
        $this->app->singleton(
            'transmorpher', $this->getTransmorpher());
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

    /**
     * Get the configured Transmorpher class from config.
     * Also make sure it implements the TransmorpherInterface.
     *
     * @return string
     * @throws InvalidTransmorpherException
     */
    protected function getTransmorpher(): string
    {
        if (is_a($transmorpherClass = config('transmorpher.transmorpher'), TransmorpherInterface::class, true)) {
            return $transmorpherClass;
        }

        throw new InvalidTransmorpherException($transmorpherClass);
    }
}
