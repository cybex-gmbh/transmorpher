<?php

namespace App\Enums;

use App\Exceptions\InvalidConfigurationException;

enum Queue: string
{
    case VIDEO_TRANSCODING = 'video_transcoding';
    case CLIENT_NOTIFICATIONS = 'client_notifications';
    case EMAIL = 'email';

    public function getQueue(): string
    {
        $queue = $this->getConfig('queue');
        $useSqsFifo = $this->useSqsFifo();

        return $useSqsFifo ? sprintf('%s.fifo', $queue) : $queue;
    }

    public function getConnection(): string
    {
        return $this->getConfig('connection');
    }

    public function useSqsFifo(): bool
    {
        return $this->getConfig('use_sqs_fifo');
    }

    /**
     * @throws InvalidConfigurationException
     */
    public function validateConfiguration(): void
    {
        $connection = $this->getConnection();
        $queue = $this->getQueue();
        $useSqsFifo = $this->useSqsFifo();

        $connectionConfig = config(sprintf('queue.connections.%s', $connection));

        if (!is_array($connectionConfig)) {
            throw new InvalidConfigurationException(sprintf('Queue connection [%s] is not configured.', $connection));
        }

        $driver = $connectionConfig['driver'] ?? null;

        if (!$driver) {
            throw new InvalidConfigurationException(sprintf('Queue connection [%s] has no valid driver configured.', $connection));
        }

        if ($useSqsFifo && $driver !== 'sqs') {
            throw new InvalidConfigurationException(sprintf(
                'Queue [%s] requires an SQS driver because FIFO is enabled. Current driver: [%s].',
                $queue,
                $driver
            ));
        }
    }

    protected function getConfig(string $key): mixed
    {
        return config(sprintf('transmorpher.queue.%s.%s', $this->value, $key));
    }
}
