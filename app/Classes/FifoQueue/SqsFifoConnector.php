<?php

namespace App\Classes\FifoQueue;

use Arr;
use Aws\Sqs\SqsClient;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\Connectors\SqsConnector;

class SqsFifoConnector extends SqsConnector
{
    /**
     * Establish a queue connection.
     *
     * @param array $config
     *
     * @return Queue
     */
    public function connect(array $config): Queue
    {
        $config = $this->getDefaultConfiguration($config);

        if (! empty($config['key']) && ! empty($config['secret'])) {
            $config['credentials'] = Arr::only($config, ['key', 'secret', 'token']);
        }

        return new SqsFifoQueue(
            new SqsClient(
                Arr::except($config, ['token'])
            ),
            $config['queue'],
            $config['prefix'] ?? '',
            $config['suffix'] ?? '',
            $config['after_commit'] ?? null
        );
    }
}
