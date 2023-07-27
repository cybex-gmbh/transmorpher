<?php

namespace App\Console\Commands;

use App\Enums\ValidationRegex;
use App\Models\User;
use Hash;
use Illuminate\Console\Command;

class CreateUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:user
                {name : The name of the user.}
                {email : The E-Mail of the user.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates a new user with a secure random password.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $email = $this->argument('email');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('The provided email is not valid!');
            return Command::INVALID;
        }

        if (User::whereEmail($email)->count()) {
            $this->error('A user with the provided email already exists!');
            return Command::INVALID;
        }

        if (User::whereName($name)->count()) {
            $this->error('A user with the provided name already exists!');
            return Command::INVALID;
        }

        // Username is used in file paths and URLs, therefore only lower/uppercase characters, numbers, underscores and hyphens are allowed.
        if (!preg_match(ValidationRegex::USERNAME->get(), $name)) {
            $this->error(sprintf('The username must match the pattern %s!', ValidationRegex::USERNAME->get()));
            return Command::INVALID;
        }

        /*
        * Laravel passwords are usually not nullable, so we will need to set something when creating the user.
        * Since we do not want to create a Password for the user, but need to store something secure,
        * we will just generate a string of random bytes.
        */
        if (!$user = User::create(['name' => $name, 'email' => $email, 'password' => Hash::make(random_bytes(300))])) {
            $this->error('There was an error when creating the user!');
            return Command::FAILURE;
        }

        $this->info(sprintf('Successfully created new user %s: %s (%s)', $user->getKey(), $user->name, $user->email));
        $this->newLine();

        $this->call('create:token', ['userId' => $user->getKey()]);

        return Command::SUCCESS;
    }
}
