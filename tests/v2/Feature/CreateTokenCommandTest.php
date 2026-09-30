<?php

namespace Tests\v2\Feature;

use App\Console\Commands\CreateToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\PendingCommand;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateTokenCommandTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    #[Test]
    public function ensureNewTokenCanBeCreated(): void
    {
        $this->createToken($this->user);

        $token = $this->user->tokens()->firstOrFail();
        $this->assertNull($token->expires_at);
    }

    #[Test]
    public function ensureDefaultExpiryIsSetForOldToken(): void
    {
        $this->createToken($this->user);
        $this->createToken($this->user);

        $oldToken = $this->user->tokens()->firstOrFail();
        $this->assertTrue($oldToken->expires_at->isSameDay(now()->addDays(7)));
    }

    #[Test]
    #[DataProvider('expiryProvider')]
    public function ensureCustomExpiryIsSetForOldToken(string $expiry, callable $expiryIsSetCorrectly): void
    {
        $this->createToken($this->user);
        $this->createToken($this->user, expiry: $expiry);

        $oldToken = $this->user->tokens()->firstOrFail();
        $this->assertTrue($expiryIsSetCorrectly($oldToken));
    }

    #[Test]
    public function ensureExpiryIsOnlySetForLatestToken(): void
    {
        $this->createToken($this->user);
        $this->createToken($this->user, expiry: '5 hours');
        $this->createToken($this->user);

        $tokens = $this->user->tokens;
        $this->assertCount(3, $tokens);

        $firstOldToken = $tokens->first();
        $this->assertTrue($firstOldToken->expires_at->isSameHour(now()->addHours(5)));

        $secondOldToken = $tokens->skip(1)->first();
        $this->assertTrue($secondOldToken->expires_at->isSameDay(now()->addDays(7)));

        $newToken = $this->user->tokens()->orderBy('id', 'desc')->firstOrFail();
        $this->assertNull($newToken->expires_at);
    }

    #[Test]
    public function ensureOldTokensArePurged(): void
    {
        $this->createToken($this->user);
        $this->createToken($this->user);

        $oldTokens = $this->user->tokens;
        $this->assertCount(2, $oldTokens);

        $this->createToken($this->user, purge: true);
        $this->assertCount(1, $this->user->refresh()->tokens);
        $this->assertNotContains($oldTokens->first(), $this->user->tokens);
    }

    public static function expiryProvider(): array
    {
        return [
            'hours' => ['5 hours', fn(PersonalAccessToken $token) => $token->expires_at->isSameHour(now()->addHours(5))],
            'days' => ['2 days', fn(PersonalAccessToken $token) => $token->expires_at->isSameDay(now()->addDays(2))],
            'week' => ['1 week', fn(PersonalAccessToken $token) => $token->expires_at->isSameDay(now()->addWeek())],
            'months' => ['3 months', fn(PersonalAccessToken $token) => $token->expires_at->isSameDay(now()->addMonths(3))],
        ];
    }

    protected function createToken(User $user, bool $purge = false, ?string $expiry = null): PendingCommand
    {
        return $this->artisan(CreateToken::class, array_filter([
            'userId' => $user->id,
            '--purge' => $purge,
            '--expiry' => $expiry,
        ]))->assertOk();
    }
}
