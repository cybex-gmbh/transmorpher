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
        Schema::table('upload_slots', function (Blueprint $table) {
            // Should be made non-nullable after v1 has been discontinued.
            $table->string('filename')->nullable()->after('identifier');
        });
    }
};

