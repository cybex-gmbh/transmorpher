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
                {email : The E-Mail of the user.}
                {api_url : The URL at which the client can receive notifications.}
                {--password= : The password of the user.}';

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
        $apiUrl = $this->argument('api_url');

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

        if (!filter_var($apiUrl, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
            $this->error(sprintf('The API URL must be a valid URL and include a path.'));
            return Command::INVALID;
        }

        /*
        * Laravel passwords are usually not nullable, so we will need to set something when creating the user.
        * Since we do not want to create a Password for the user, but need to store something secure,
        * we will just generate a string of random bytes.
        * This needs to be encoded to base64 because null bytes are not accepted anymore (PHP 8.3).
        */
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'api_url' => $apiUrl,
            'password' => Hash::make($this->option('password') ?: base64_encode(random_bytes(300)))
        ]);

        $this->info(sprintf('Successfully created new user %s: %s (%s)', $user->getKey(), $user->name, $user->email));
        $this->newLine();

        $this->call('create:token', ['userId' => $user->getKey()]);

        return Command::SUCCESS;
    }
}
