<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('upload_slots', function (Blueprint $table) {
            $table->id();
            $table->string('token')->unique()->nullable();
            $table->string('identifier')->unique();
            $table->string('callback_url')->nullable();
            $table->string('validation_rules')->nullable();
            $table->dateTime('valid_until')->nullable();
            $table->enum('media_type', ['image', 'video']);
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
        });
    }
};
