<?php

namespace App\Helpers;

use App\Interfaces\CloudStorageInterface;
use Streaming\Clouds\S3;

class S3Helper implements CloudStorageInterface
{
    /**
     * Returns the configuration for opening data from the cloud storage.
     *
     * @param string $key
     *
     * @return array
     */
    public function getOpenConfiguration(string $key): array
    {
        return [
            'cloud'   => $this->getS3(),
            'options' => [
                'Bucket' => config('filesystems.disks.s3Main.bucket'),
                'Key'    => $key,
            ],
        ];
    }

    /**
     * Returns the configuration for saving data to the cloud storage.
     *
     * @param string $destinationPath
     * @param string $fileName
     *
     * @return array
     */
    public function getSaveConfiguration(string $destinationPath, string $fileName): array
    {
        return [
            'cloud'   => $this->getS3(),
            'options' => [
                'dest'     => sprintf('s3://%s/%s',
                    config('filesystems.disks.%s.bucket', config('transmorpher.disks.videoDerivatives')),
                    $destinationPath
                ),
                'filename' => $fileName,
            ],
        ];
    }

    protected function getS3(): S3
    {
        return new S3(
            [
                'version'     => 'latest',
                'region'      => config('transmorpher.aws.region'),
                'credentials' => [
                    'key'    => config('transmorpher.aws.key'),
                    'secret' => config('transmorpher.aws.secret'),
                ],
            ]
        );
    }
}
