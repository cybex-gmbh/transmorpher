<?php

namespace App\Console\Commands;

use App\Mail\ApiVersionDeprecationNotice;
use App\Models\User;
use Illuminate\Console\Command;
use Mail;

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
        Mail::to(config('mail.from.address'))
            ->bcc(User::get())
            ->queue(app(ApiVersionDeprecationNotice::class, ['apiVersion' => 1]));

        return Command::SUCCESS;
    }
}
