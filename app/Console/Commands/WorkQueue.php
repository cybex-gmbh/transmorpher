<?php

namespace App\Console\Commands;

use App\Enums\Queue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use Throwable;

class WorkQueue extends Command
{
    protected $signature = 'transmorpher:queue-work
                {target : Queue target (video_transcoding, client_notifications, email).}
                {--once : Process only one job and then exit.}';

    protected $description = 'Validate queue configuration and start a queue worker.';

    public function handle(): int
    {
        try {
            $target = $this->resolveTarget($this->argument('target'));

            $target->validateConfiguration();

            $connection = $target->getConnection();
            $queue = $target->getQueue();

            $this->info(sprintf(
                'Starting queue worker for target [%s] on connection [%s] and queue [%s].',
                $target->name,
                $connection,
                $queue
            ));

            return Artisan::call('queue:work', [
                'connection' => $connection,
                '--queue' => $queue,
                '--once' => $this->option('once'),
            ], outputBuffer: $this->getOutput());
        } catch (Throwable $throwable) {
            report($throwable);
            $this->error($throwable->getMessage());

            return Command::FAILURE;
        }
    }

    protected function resolveTarget(string $target): Queue
    {
        return Queue::tryFrom($target)
            ?? throw new InvalidArgumentException(sprintf(
                'Invalid queue target [%s]. Allowed values: %s.',
                $target,
                implode(', ', array_column(Queue::cases(), 'value'))
            ));
    }
}
