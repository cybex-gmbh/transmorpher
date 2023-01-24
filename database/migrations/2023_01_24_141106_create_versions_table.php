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
        Schema::create('versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('number');
            $table->string('path');
            $table->boolean('processed')->default(0);
            $table->foreignId('media_id')->constrained();
            $table->timestamps();

            $table->unique(['number', 'media_id']);
        });
    }
};
