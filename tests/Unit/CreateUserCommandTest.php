<?php

namespace Tests\Unit;

use App\Console\Commands\CreateUser;
use App\Models\User;
use Artisan;
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
    public function ensureUserGetsCreatedIncludingSanctumToken()
    {
        $this->assertEmpty(User::get());

        $exitStatus = Artisan::call(CreateUser::class, ['name' => self::NAME, 'email' => self::EMAIL]);

        $this->assertEquals(0, $exitStatus, 'Command did not exit successfully.');
        $this->assertNotNull(User::first(), 'User was not created.');
        $this->assertEquals(self::NAME, User::first()->name, 'Name does not match.');
        $this->assertEquals(self::EMAIL, User::first()->email, 'Email does not match.');
        $this->assertNotEmpty(User::first()->tokens, 'Laravel Sanctum Token was not created.');
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

    /**
     * @test
     * @dataProvider duplicateEntryDataProvider
     */
    public function failOnDuplicateEntry(string $name, string $email)
    {
        $exitStatus = Artisan::call(CreateUser::class, ['name' => self::NAME, 'email' => self::EMAIL]);
        $this->assertEquals(0, $exitStatus, 'Command did not exit successfully.');

        $exitStatus = Artisan::call(CreateUser::class, ['name' => $name, 'email' => $email]);
        $this->assertEquals(2, $exitStatus, 'Command did not fail.');
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
}
