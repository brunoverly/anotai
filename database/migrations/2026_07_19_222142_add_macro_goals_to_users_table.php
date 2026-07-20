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
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('calories_kcal', 6, 2)->nullable();
            $table->decimal('carbohydrate_g', 5, 2)->nullable();
            $table->decimal('protein_g', 5, 2)->nullable();
            $table->decimal('fat_g', 5, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['calories_kcal', 'carbohydrate_g', 'protein_g', 'fat_g']);
        });
    }
};
