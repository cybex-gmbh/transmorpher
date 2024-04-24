<?php

namespace Tests\Unit;

use App\Console\Commands\CreateUser;
use App\Models\User;
use Artisan;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    protected const NAME = 'Oswald';
    protected const EMAIL = 'oswald@example.com';
    protected const API_URL = 'http://example.com/transmorpher/notifications';

    #[Test]
    public function ensureUserCanBeCreated()
    {
        $this->assertDatabaseMissing(User::getModel()->getTable(), ['name' => self::NAME, 'email' => self::EMAIL]);

        $exitStatus = $this->createUser();

        $this->assertEquals(Command::SUCCESS, $exitStatus);
        $this->assertDatabaseHas(User::getModel()->getTable(), ['name' => self::NAME, 'email' => self::EMAIL]);
    }

    #[Test]
    public function ensureUserHasSanctumToken()
    {
        $this->createUser();

        $this->assertNotEmpty(User::whereName(self::NAME)->first()->tokens);
    }

    #[Test]
    #[DataProvider('duplicateEntryDataProvider')]
    public function failOnDuplicateEntry(string $name, string $email)
    {
        $this->createUser();
        $exitStatus = $this->createUser($name, $email);

        $this->assertEquals(Command::INVALID, $exitStatus);
    }

    #[Test]
    #[DataProvider('missingArgumentsDataProvider')]
    public function failOnMissingArguments(?string $name, ?string $email, ?string $apiUrl)
    {
        $arguments = [];
        $name && $arguments['name'] = $name;
        $email && $arguments['email'] = $email;
        $apiUrl && $arguments['api_url'] = $apiUrl;

        $this->expectException(RuntimeException::class);
        Artisan::call(CreateUser::class, $arguments);
    }

    #[Test]
    #[DataProvider('invalidArgumentsDataProvider')]
    public function failOnInvalidArguments(string $name, string $email, string $apiUrl)
    {
        $exitStatus = $this->createUser($name, $email, $apiUrl);

        $this->assertEquals(Command::INVALID, $exitStatus);
    }

    public static function duplicateEntryDataProvider(): array
    {
        return [
            'duplicate name' => [
                'name' => self::NAME,
                'email' => 'email2@example.com'
            ],
            'duplicate email' => [
                'name' => 'name2',
                'email' => self::EMAIL
            ],
            'duplicate name and email' => [
                'name' => self::NAME,
                'email' => self::EMAIL
            ]
        ];
    }

    public static function missingArgumentsDataProvider(): array
    {
        return [
            'missing name' => [
                'name' => null,
                'email' => self::EMAIL,
                'apiUrl' => self::API_URL
            ],
            'missing email' => [
                'name' => self::NAME,
                'email' => null,
                'apiUrl' => self::API_URL
            ],
            'missing api url' => [
                'name' => self::NAME,
                'email' => self::EMAIL,
                'apiUrl' => null
            ],
            'missing name and email' => [
                'name' => null,
                'email' => null,
                'apiUrl' => self::API_URL
            ],
            'missing name and api url' => [
                'name' => null,
                'email' => self::EMAIL,
                'apiUrl' => null
            ],
            'missing email and api url' => [
                'name' => self::NAME,
                'email' => null,
                'apiUrl' => null
            ]
        ];
    }

    public static function invalidArgumentsDataProvider(): array
    {
        return [
            'invalid name with slash' => [
                'name' => 'invalid/name',
                'email' => self::EMAIL,
                'apiUrl' => self::API_URL
            ],
            'invalid name with backslash' => [
                'name' => 'invalid\name',
                'email' => self::EMAIL,
                'apiUrl' => self::API_URL
            ],
            'invalid name with dot' => [
                'name' => 'invalid.name',
                'email' => self::EMAIL,
                'apiUrl' => self::API_URL
            ],
            'invalid name with hyphen' => [
                'name' => 'invalid--name',
                'email' => self::EMAIL,
                'apiUrl' => self::API_URL
            ],
            'invalid name with trailing hyphen' => [
                'name' => 'invalidName-',
                'email' => self::EMAIL,
                'apiUrl' => self::API_URL
            ],
            'invalid name with special character' => [
                'name' => 'invalidName!',
                'email' => self::EMAIL,
                'apiUrl' => self::API_URL
            ],
            'invalid name with umlaut' => [
                'name' => 'invalidNäme',
                'email' => self::EMAIL,
                'apiUrl' => self::API_URL
            ],
            'invalid name with space' => [
                'name' => 'invalid name',
                'email' => self::EMAIL,
                'apiUrl' => self::API_URL
            ],
            'invalid email' => [
                'name' => self::NAME,
                'email' => 'invalidEmail',
                'apiUrl' => self::API_URL
            ],
            'invalid name and email' => [
                'name' => 'invalid/name',
                'email' => 'invalidEmail',
                'apiUrl' => self::API_URL
            ],
            'invalid api url no scheme' => [
                'name' => self::NAME,
                'email' => self::EMAIL,
                'apiUrl' => 'example.com/transmorpher/notifications'
            ],
            'invalid api url no path' => [
                'name' => self::NAME,
                'email' => self::EMAIL,
                'apiUrl' => 'https://example.com'
            ],
            'invalid api url no scheme and no path' => [
                'name' => self::NAME,
                'email' => self::EMAIL,
                'apiUrl' => 'example.com'
            ],
            'invalid name and email and apiUrl' => [
                'name' => 'invalid/name',
                'email' => 'invalidEmail',
                'apiUrl' => 'example.com'
            ]
        ];
    }

    /**
     * @param string|null $name
     * @param string|null $email
     * @param string|null $apiUrl
     * @return int
     */
    protected function createUser(?string $name = null, ?string $email = null, ?string $apiUrl = null): int
    {
        return Artisan::call(CreateUser::class, ['name' => $name ?? self::NAME, 'email' => $email ?? self::EMAIL, 'api_url' => $apiUrl ?? self::API_URL]);
    }
}
