<?php

namespace App\Classes\FifoQueue;

use Illuminate\Queue\SqsQueue;

class SqsFifoQueue extends SqsQueue
{
    /**
     * Push a raw payload onto the queue.
     *
     * @param  string  $payload
     * @param  string|null  $queue
     * @param  array  $options
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = []): mixed
    {
        return $this->sqs->sendMessage([
            'QueueUrl' => $this->getQueue($queue),
            'MessageBody' => $payload,
            'MessageGroupId' => uniqid(),
            'MessageDeduplicationId' => uniqid(),
        ])->get('MessageId');
    }
}
