<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('api_url')->comment('The URL at which the client can receive notifications.');
        });

        Schema::table('upload_slots', function (Blueprint $table) {
            $table->dropColumn('callback_url');
        });
    }
};
