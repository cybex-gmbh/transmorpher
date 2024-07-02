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
            'name' => env('PULLPREVIEW_USER_NAME', 'pullpreview'),
            'email' => env('PULLPREVIEW_USER_EMAIL', 'pullpreview@example.com'),
            'api_url' => env('PULLPREVIEW_USER_API_URL', 'http://amigor/transmorpher/notifications'),
            'password' => env('PULLPREVIEW_USER_PASSWORD')
        ]);

        DB::table('personal_access_tokens')->where('id', 1)->update(['token' => env('TRANSMORPHER_AUTH_TOKEN_HASH')]);
    }
}
