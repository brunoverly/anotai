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
        Schema::create('foods', function (Blueprint $table) {
            $table->id();

            $table->string('name')->unique()->index();

            $table->decimal('protein_g', 5, 2)->default(0.00);
            $table->decimal('carbohydrate_g', 5, 2)->default(0.00);
            $table->decimal('fat_g', 5, 2)->default(0.00);
            $table->integer('calories_kcal')->default(0);

            $table->string('source')->default('local');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('foods');
    }
};
