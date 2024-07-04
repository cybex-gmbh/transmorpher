<?php

namespace Database\Seeders;

use Artisan;
use DB;
use Illuminate\Database\Seeder;

class PullpreviewSeeder extends Seeder
{
    /**
     * Seeds the database with a default user used within a Pullpreview environment.
     *
     * @return void
     */
    public function run(): void
    {
        Artisan::call('create:user', [
            'name' => env('SEED_USER_NAME'),
            'email' => env('SEED_USER_EMAIL'),
            'api_url' => sprintf('http://%s/%s', env('CLIENT_CONTAINER_NAME'), env('CLIENT_NOTIFICATION_ROUTE', 'transmorpher/notifications')),
            '--password' => env('SEED_USER_PASSWORD')
        ]);

        DB::table('personal_access_tokens')->where('id', 1)->update(['token' => env('TRANSMORPHER_AUTH_TOKEN_HASH')]);
    }
}
