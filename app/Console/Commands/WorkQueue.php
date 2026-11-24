<?php

namespace App\Console\Commands;

use App\Enums\Queue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class WorkQueue extends Command
{
    protected $signature = 'transmorpher:queue-work
                {queue : Queue identifier for which this worker should be started (video_transcoding, client_notifications, email).}
                {--once : Process only one job and then exit.}';

    protected $description = 'Validate queue configuration and start a queue worker.';

    public function handle(): int
    {
        try {
            $queue = Queue::fromWithFeedback($this->argument('queue'));
            $queueConnection = $queue->getConnection();
            $queueName = $queue->getName();

            $this->info(sprintf(
                'Starting queue worker for [%s] on connection [%s] with queue name [%s].',
                $queue->name,
                $queueConnection,
                $queueName
            ));

            return Artisan::call('queue:work', [
                'connection' => $queueConnection,
                '--queue' => $queueName,
                '--once' => $this->option('once'),
            ], outputBuffer: $this->getOutput());
        } catch (Throwable $throwable) {
            report($throwable);
            $this->error($throwable->getMessage());

            return Command::FAILURE;
        }
    }
}
