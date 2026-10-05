<?php

namespace App\Console\Commands;

use App\Models\User;
use Carbon\Exceptions\InvalidFormatException;
use Carbon\Exceptions\InvalidIntervalException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use function Laravel\Prompts\callout;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;

#[Signature(
    'create:token
        {userId : The user id the token is created for}
        {--purge : Delete all old Transmorpher tokens before creating a new one}
        {--expiry=7 days : Duration after which the old token expires (e.g., "2 hours", "1 day", "2 weeks")}'
)]
#[Description('Creates a new Laravel Sanctum token for a specified user id. By default, the old token will expire in 7 days')]
class CreateToken extends Command implements PromptsForMissingInput
{
    protected User $user;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->retrieveUser();

        if ($this->option('purge')) {
            $this->purgeTokens();
        } else {
            $this->setTokenExpiry();
        }

        $this->createNewToken();

        return 0;
    }

    protected function purgeTokens(): void
    {
        $this->user->tokens()->where('name', 'transmorpher')->delete();

        note('Deleted all old Transmorpher tokens.');
    }

    protected function setTokenExpiry(): void
    {
        try {
            $expiryTime = now()->add($this->option('expiry'));
        } catch (InvalidIntervalException|InvalidFormatException) {
            $this->fail(sprintf('Invalid expiry: "%s". Try "5 hours, "1 day", "2 weeks", "1 month", ...', $this->option('expiry')));
        }

        $latestToken = $this->user->tokens()->where('name', 'transmorpher')->orderBy('id', 'desc')->first();

        if ($latestToken) {
            $latestToken->update(['expires_at' => $expiryTime]);

            note(sprintf('Set expiry to %s for the old token.', $expiryTime));
        }
    }

    protected function createNewToken(): void
    {
        $token = $this->user->createToken('transmorpher', ['transmorpher:*']);

        callout(
            label: sprintf('New token for the user %s: %s (%s)', $this->user->getKey(), $this->user->name, $this->user->email),
            content: sprintf('TRANSMORPHER_AUTH_TOKEN="%s"', $token->plainTextToken),
        );
        warning('The quotation marks at the start and end of the token are necessary!');
    }

    /**
     * @return void
     */
    public function retrieveUser(): void
    {
        try {
            $this->user = User::findOrFail($this->argument('userId'));
        } catch (ModelNotFoundException) {
            $this->fail(sprintf('No record for User with id %s.', $this->argument('userId')));
        }
    }
}
