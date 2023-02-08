<?php

namespace App\Interfaces;

interface CloudStorageInterface
{
    /**
     * Returns the configuration for opening data from the cloud storage.
     *
     * @param string $key
     *
     * @return array
     */
    public function getOpenConfiguration(string $key): array;

    /**
     * Returns the configuration for saving data to the cloud storage.
     *
     * @param string $destinationPath
     * @param string $fileName
     *
     * @return array
     */
    public function getSaveConfiguration(string $destinationPath, string $fileName): array;
}
