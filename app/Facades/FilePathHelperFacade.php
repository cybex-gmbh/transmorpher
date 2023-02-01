<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class FilePathHelperFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'file.path';
    }
}
