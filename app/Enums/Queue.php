<?php

namespace App\Enums;

use App\Exceptions\InvalidConfigurationException;
use InvalidArgumentException;

enum Queue: string
{
    case VIDEO_TRANSCODING = 'video_transcoding';
    case CLIENT_NOTIFICATIONS = 'client_notifications';
    case EMAIL = 'email';

    public static function fromWithFeedback(string $value): self
    {
        return Queue::tryFrom($value)
            ?? throw new InvalidArgumentException(sprintf(
                'Invalid enum value %s. Allowed values: %s.',
                $value,
                implode(', ', array_column(Queue::cases(), 'value'))
            ));
    }

    public function getName(): string
    {
        $this->validateConfiguration();

        return $this->name();
    }

    public function getConnection(): string
    {
        $this->validateConfiguration();

        return $this->connection();
    }

    protected function useSqsFifo(): bool
    {
        return $this->getConfig('use_sqs_fifo');
    }

    /**
     * @throws InvalidConfigurationException
     */
    protected function validateConfiguration(): void
    {
        $connection = $this->connection();
        $connectionConfig = config(sprintf('queue.connections.%s', $connection));

        if (!is_array($connectionConfig)) {
            throw new InvalidConfigurationException(sprintf('Queue connection [%s] is not configured.', $connection));
        }

        $driver = $connectionConfig['driver'] ?? null;

        if (!$driver) {
            throw new InvalidConfigurationException(sprintf('Queue connection [%s] has no valid driver configured.', $connection));
        }

        if ($this->useSqsFifo() && $driver !== 'sqs') {
            throw new InvalidConfigurationException(sprintf(
                'Queue [%s] requires an SQS driver because FIFO is enabled. Current driver: [%s].',
                $this->name,
                $driver
            ));
        }
    }

    protected function name(): string
    {
        $queue = $this->getConfig('queue');
        $useSqsFifo = $this->useSqsFifo();

        return $useSqsFifo ? sprintf('%s.fifo', $queue) : $queue;
    }

    protected function connection(): string
    {
        return $this->getConfig('connection');
    }

    protected function getConfig(string $key): mixed
    {
        return config(sprintf('transmorpher.queue.%s.%s', $this->value, $key));
    }
}
