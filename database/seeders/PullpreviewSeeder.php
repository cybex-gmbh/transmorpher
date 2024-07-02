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
        Artisan::call(
            sprintf('create:user %s %s %s', env('PULLPREVIEW_USER_NAME', 'pullpreview'), env('PULLPREVIEW_USER_EMAIL', 'pullpreview@example.com'), env('PULLPREVIEW_USER_API_URL', 'http://amigor/transmorpher/notifications'))
        );

        DB::table('personal_access_tokens')->where('id', 1)->update(['token' => env('TRANSMORPHER_AUTH_TOKEN_HASH')]);
    }
}
