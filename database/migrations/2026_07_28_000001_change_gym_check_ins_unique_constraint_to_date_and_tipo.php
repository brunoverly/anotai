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
        Schema::table('gym_check_ins', function (Blueprint $table) {
            $table->dropUnique(['check_in_date']);
            $table->unique(['check_in_date', 'tipo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gym_check_ins', function (Blueprint $table) {
            $table->dropUnique(['check_in_date', 'tipo']);
            $table->unique(['check_in_date']);
        });
    }
};
