<?php

namespace Tests\Unit;

use App\Console\Commands\CreateUser;
use App\Models\User;
use Artisan;
use RuntimeException;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    protected const NAME = 'Oswald';
    protected const EMAIL = 'oswald@example.com';
    protected static bool $initialized = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$initialized) {
            self::$initialized = true;

            User::first()?->delete();
        }
    }

    /**
     * @test
     */
    public function ensureUserCanBeCreated()
    {
        $this->assertEmpty(User::get());

        $exitStatus = Artisan::call(CreateUser::class, ['name' => self::NAME, 'email' => self::EMAIL]);

        $this->assertEquals(0, $exitStatus);
        $this->assertNotNull(User::first());
    }

    /**
     * @test
     * @depends ensureUserCanBeCreated
     */
    public function ensureUserGetsCreatedWithCorrectArguments()
    {
        $this->assertEquals(
            [self::NAME, self::EMAIL],
            [User::first()->name, User::first()->email]
        );
    }

    /**
     * @test
     * @depends ensureUserCanBeCreated
     */
    public function ensureUserHasSanctumToken()
    {
        $this->assertNotEmpty(User::first()->tokens);
    }

    /**
     * @test
     * @dataProvider duplicateEntryDataProvider
     * @depends ensureUserCanBeCreated
     */
    public function failOnDuplicateEntry(string $name, string $email)
    {
        $exitStatus = Artisan::call(CreateUser::class, ['name' => $name, 'email' => $email]);
        $this->assertEquals(2, $exitStatus, 'Command did not fail.');
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

        $this->assertEquals(2, $exitStatus, 'Command did not fail.');
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
            'invalid name' => [
                'name' => '--invalidName',
                'email' => self::EMAIL
            ],
            'invalid email' => [
                'name' => self::NAME,
                'email' => 'invalidEmail'
            ],
            'invalid name and email' => [
                'name' => '--invalidName',
                'email' => 'invalidEmail'
            ]
        ];
    }
}
