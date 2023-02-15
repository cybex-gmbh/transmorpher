<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CreateSigningKeypair extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transmorpher:keypair';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates a Sodium keypair which is used for signing requests to the client package.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $keyPair = sodium_crypto_sign_keypair();

        $this->info('Successfully generated Sodium signing key pair.');
        $this->info('Please write the key pair into the .env file!');
        $this->warn(sprintf('TRANSMORPHER_SIGNING_KEYPAIR=%s', sodium_bin2hex($keyPair)));
    }
}
