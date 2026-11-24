<?php

namespace Tests\v2\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserTokenWildcardTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    #[Test]
    #[DataProvider('allowedAbilityProvider')]
    public function grantsMatchingAbilities(array $grantedAbilities, string $requestedAbility): void
    {
        $this->actingAsWithAbilities($grantedAbilities);

        $this->assertTrue($this->user->tokenCanWithWildcard($requestedAbility));
    }

    #[Test]
    #[DataProvider('deniedAbilityProvider')]
    public function deniesNonMatchingAbilities(array $grantedAbilities, string $requestedAbility): void
    {
        $this->actingAsWithAbilities($grantedAbilities);

        $this->assertFalse($this->user->tokenCanWithWildcard($requestedAbility));
    }

    public static function allowedAbilityProvider(): array
    {
        return [
            'exact ability' => [['transmorpher:upload.receive'], 'transmorpher:upload.receive'],
            'global wildcard' => [['*'], 'transmorpher:upload.receive'],
            'scope wildcard upload' => [['transmorpher:*'], 'transmorpher:upload.receive'],
            'scope wildcard version' => [['transmorpher:*'], 'transmorpher:version.set'],
            'nested wildcard receive' => [['transmorpher:upload.*'], 'transmorpher:upload.receive'],
            'nested wildcard complete' => [['transmorpher:upload.*'], 'transmorpher:upload.complete'],
        ];
    }

    public static function deniedAbilityProvider(): array
    {
        return [
            'nested wildcard cannot grant sibling branch' => [['transmorpher:upload.*'], 'transmorpher:version.set'],
            'nested wildcard cannot grant other top-level scope' => [['transmorpher:upload.*'], 'other-scope:upload.receive'],
            'scope wildcard cannot grant other top-level scope' => [['transmorpher:*'], 'other-scope:upload.receive'],
        ];
    }

    protected function actingAsWithAbilities(array $abilities): void
    {
        Sanctum::actingAs($this->user, $abilities);
    }
}
