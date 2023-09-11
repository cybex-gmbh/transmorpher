<?php

namespace Tests\Unit;

use App\Console\Commands\CreateUser;
use App\Models\User;
use Artisan;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    protected const NAME = 'Oswald';
    protected const EMAIL = 'oswald@example.com';

    /**
     * @test
     */
    public function ensureUserCanBeCreated()
    {
        $this->assertDatabaseMissing(User::getModel()->getTable(), ['name' => self::NAME, 'email' => self::EMAIL]);

        $exitStatus = Artisan::call(CreateUser::class, ['name' => self::NAME, 'email' => self::EMAIL]);

        $this->assertEquals(Command::SUCCESS, $exitStatus);
        $this->assertDatabaseHas(User::getModel()->getTable(), ['name' => self::NAME, 'email' => self::EMAIL]);
    }

    /**
     * @test
     */
    public function ensureUserHasSanctumToken()
    {
        Artisan::call(CreateUser::class, ['name' => self::NAME, 'email' => self::EMAIL]);
        $this->assertNotEmpty(User::whereName(self::NAME)->first()->tokens);
    }

    /**
     * @test
     * @dataProvider duplicateEntryDataProvider
     */
    public function failOnDuplicateEntry(string $name, string $email)
    {
        Artisan::call(CreateUser::class, ['name' => self::NAME, 'email' => self::EMAIL]);
        $exitStatus = Artisan::call(CreateUser::class, ['name' => $name, 'email' => $email]);
        $this->assertEquals(Command::INVALID, $exitStatus);
    }

    /**
     * @test
     * @dataProvider missingArgumentsDataProvider
     */
    public function failOnMissingArguments(?string $name, ?string $email)
    {
        $arguments = [];
        $name && $arguments['name'] = $name;
        $email && $arguments['email'] = $email;

        $this->expectException(RuntimeException::class);
        Artisan::call(CreateUser::class, $arguments);
    }

    /**
     * @test
     * @dataProvider invalidArgumentsDataProvider
     */
    public function failOnInvalidArguments(string $name, string $email)
    {
        $exitStatus = Artisan::call(CreateUser::class, ['name' => $name, 'email' => $email]);

        $this->assertEquals(Command::INVALID, $exitStatus);
    }

    protected function duplicateEntryDataProvider(): array
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

    protected function missingArgumentsDataProvider(): array
    {
        return [
            'missing name' => [
                'name' => null,
                'email' => self::EMAIL
            ],
            'missing email' => [
                'name' => self::NAME,
                'email' => null
            ],
            'missing name and email' => [
                'name' => null,
                'email' => null
            ]
        ];
    }

    protected function invalidArgumentsDataProvider(): array
    {
        return [
            'invalid name with slash' => [
                'name' => 'invalid/name',
                'email' => self::EMAIL
            ],
            'invalid name with backslash' => [
                'name' => 'invalid\name',
                'email' => self::EMAIL
            ],
            'invalid name with dot' => [
                'name' => 'invalid.name',
                'email' => self::EMAIL
            ],
            'invalid name with hyphen' => [
                'name' => 'invalid--name',
                'email' => self::EMAIL
            ],
            'invalid name with trailing hyphen' => [
                'name' => 'invalidName-',
                'email' => self::EMAIL
            ],
            'invalid name with special character' => [
                'name' => 'invalidName!',
                'email' => self::EMAIL
            ],
            'invalid name with umlaut' => [
                'name' => 'invalidNäme',
                'email' => self::EMAIL
            ],
            'invalid name with space' => [
                'name' => 'invalid name',
                'email' => self::EMAIL
            ],
            'invalid email' => [
                'name' => self::NAME,
                'email' => 'invalidEmail'
            ],
            'invalid name and email' => [
                'name' => 'invalid/name',
                'email' => 'invalidEmail'
            ]
        ];
    }
}
