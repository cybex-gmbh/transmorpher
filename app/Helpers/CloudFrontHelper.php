<?php

namespace App\Helpers;

use App\Interfaces\CdnHelperInterface;
use Aws\CloudFront\CloudFrontClient;

class CloudFrontHelper implements CdnHelperInterface
{
    /**
     * Create a CDN invalidation.
     *
     * @param string $invalidationPath
     *
     * @return void
     */
    public function createInvalidation(string $invalidationPath): void
    {
        $cloudFrontClient = new CloudFrontClient([
            'version'     => 'latest',
            'region'      => config('transmorpher.aws.region'),
            'credentials' => [
                'key'    => config('transmorpher.aws.key'),
                'secret' => config('transmorpher.aws.secret'),
            ],
        ]);

        $cloudFrontClient->createInvalidation([
            'DistributionId'    => config('transmorpher.aws.cloudfront_distribution_id'),
            'InvalidationBatch' => [
                'CallerReference' => static::getCallerReference(),
                'Paths'           => [
                    'Items'    => [$invalidationPath],
                    'Quantity' => 1,
                ],
            ],
        ]);
    }

    /**
     * Return whether the CDN is configured.
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return config('transmorpher.aws.cloudfront_distribution_id') ?? false;
    }

    /**
     * Returns a unique caller reference used in the invalidation request for CloudFront.
     *
     * @return string
     */
    protected static function getCallerReference(): string
    {
        return uniqid();
    }

}
