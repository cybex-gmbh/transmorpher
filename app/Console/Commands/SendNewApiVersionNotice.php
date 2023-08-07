<?php

namespace App\Console\Commands;

use App\Notifications\NewApiVersionNotice;
use Illuminate\Console\Command;
use Notification;

class SendNewApiVersionNotice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:new-api-version-notice
                {apiVersion : The newly released API version this notice is sent for.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a notice regarding a newly released API version to all clients.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Notification::route('mail', config('mail.from.address'))
            ->notify(app(NewApiVersionNotice::class, ['apiVersion' => $this->argument('apiVersion')]));

        return Command::SUCCESS;
    }
}
