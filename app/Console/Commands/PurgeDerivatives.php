<?php

namespace App\Console\Commands;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Jobs\ClientPurgeNotification;
use App\Models\User;
use Illuminate\Console\Command;

class PurgeDerivatives extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'purge:derivatives
      {--image : Delete image derivatives.}
      {--document : Delete document derivatives.}
      {--video : Re-generate video derivatives.}
      {--a|all : Purge all derivatives.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge all derivatives, increment the cache invalidation counter and notify all clients';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!$this->option('image') && !$this->option('document') && !$this->option('video') && !$this->option('all')) {
            $this->warn(sprintf('No options provided. Call "php artisan %s --help" for a list of all options.', $this->name));
            return Command::SUCCESS;
        }

        foreach (MediaType::cases() as $mediaType) {
            if ($this->option('all') || $this->option($mediaType->value)) {
                ['success' => $success, 'message' => $message] = $mediaType->handler()->deleteDerivatives();
                $success ? $this->info($message) : $this->error($message);
            }
        }

        $originalsDisk = MediaStorage::ORIGINALS->getDisk();
        $cacheInvalidationCounterFilePath = config('transmorpher.cache_invalidation_counter_file_path');

        if (!$originalsDisk->put($cacheInvalidationCounterFilePath, $originalsDisk->get($cacheInvalidationCounterFilePath) + 1)) {
            $this->error(sprintf('Failed to update cache invalidation counter at path %s on disk %s', $cacheInvalidationCounterFilePath, MediaStorage::ORIGINALS->value));
        }

        foreach (User::get() as $user) {
            ClientPurgeNotification::dispatch($user, $originalsDisk->get($cacheInvalidationCounterFilePath));
        }

        return Command::SUCCESS;
    }
}
