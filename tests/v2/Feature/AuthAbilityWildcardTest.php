<?php

namespace Tests\v2\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthAbilityWildcardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[DataProvider('allowedAbilityProvider')]
    public function allowsProtectedRequestsForMatchingWildcardAbilities(array $abilities): void
    {
        Sanctum::actingAs(User::factory()->create(), $abilities);

        $response = $this->postJson('/api/v2/image/upload/reserve', [
            'identifier' => 'wildcard-auth-test',
            'filename' => 'wildcard-auth-test.png',
        ]);

        $response->assertSuccessful();
    }

    #[Test]
    public function rejectsProtectedRequestsForNonMatchingWildcardAbilities(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['transmorpher:version.*']);

        $response = $this->postJson('/api/v2/image/upload/reserve', [
            'identifier' => 'wildcard-auth-test',
            'filename' => 'wildcard-auth-test.png',
        ]);

        $response->assertStatus(403);
    }

    public static function allowedAbilityProvider(): array
    {
        return [
            'exact ability' => [['transmorpher:upload.reserve']],
            'global wildcard' => [['*']],
            'scope wildcard' => [['transmorpher:*']],
            'nested wildcard' => [['transmorpher:upload.*']],
        ];
    }
}
