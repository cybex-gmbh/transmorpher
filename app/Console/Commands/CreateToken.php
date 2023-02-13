<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:token
                {userId : The user id the token is created for.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates a Laravel Sanctum token for a specified user id.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $user = User::findOrFail($this->argument('userId'));
        $user->tokens()->delete();

        $userInformation = sprintf('%s: %s (%s)', $user->getKey(), $user->name, $user->email);
        $token           = $user->createToken('transmorpher');

        $this->warn(sprintf('Token for the user %s', $userInformation));
        $this->info(sprintf('TRANSMORPHER_AUTH_TOKEN="%s"', $token->plainTextToken));
        $this->warn('The quotation marks at the start and end of the token are necessary!');
    }
}
