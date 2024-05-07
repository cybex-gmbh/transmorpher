<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('personal_access_tokens')->where('tokenable_type', 'App\Models\User')->update(['tokenable_type' => 'user']);
    }
};
