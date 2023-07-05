<?php

namespace App\Console\Commands;

use App\Notifications\ApiVersionDeprecationNotice;
use Illuminate\Console\Command;
use Notification;

class SendApiVersionDeprecationNotice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:deprecation-notice
                {apiVersion : The API version this deprecation notice is send for.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a deprecation notice for a specified API version to all clients.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Notification::route('mail', config('mail.from.address'))
            ->notify(app(ApiVersionDeprecationNotice::class, ['apiVersion' => $this->argument('apiVersion')]));

        return Command::SUCCESS;
    }
}
