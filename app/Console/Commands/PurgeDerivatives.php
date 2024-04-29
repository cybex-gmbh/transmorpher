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
      {--video : Re-generate video derivatives.}
      {--a|all : Purge all derivatives.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge all derivatives, increment the cacheInvalidationRevision and notify all clients';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!$this->option('image') && !$this->option('video') && !$this->option('all')) {
            $this->warn(sprintf('No options provided. Call "php artisan %s --help" for a list of all options.', $this->name));
            return Command::SUCCESS;
        }

        foreach (MediaType::cases() as $mediaType) {
            if ($this->option('all') || $this->option($mediaType->value)) {
                ['success' => $success, 'message' => $message] = $mediaType->handler()->purgeDerivatives();
                $success ? $this->info($message) : $this->error($message);
            }
        }

        $originalsDisk = MediaStorage::ORIGINALS->getDisk();
        $cacheInvalidationFilePath = MediaStorage::getCacheInvalidationFilePath();

        if (!$originalsDisk->put($cacheInvalidationFilePath, $originalsDisk->get($cacheInvalidationFilePath) + 1)) {
            $this->error(sprintf('Failed to update cache invalidation revision at path %s on disk %s', $cacheInvalidationFilePath, MediaStorage::ORIGINALS->value));
        }

        foreach (User::get() as $user) {
            ClientPurgeNotification::dispatch($user, $originalsDisk->get($cacheInvalidationFilePath));
        }

        return Command::SUCCESS;
    }
}