<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableWithColumn = ['upload_slots' => 'media_type', 'media' => 'type'];

        foreach ($tableWithColumn as $tableName => $columnName) {
            Schema::table($tableName, function (Blueprint $table) use ($columnName) {
                $table->enum($columnName, ['image', 'video', 'pdf'])->change();
            });
        }
    }
};
