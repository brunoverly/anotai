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
        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('telegram_update_id')->nullable();
            $table->text('raw_text')->nullable();
            $table->json('items');
            $table->decimal('total_protein_g', 6, 2)->default(0);
            $table->decimal('total_carbohydrate_g', 6, 2)->default(0);
            $table->decimal('total_fat_g', 6, 2)->default(0);
            $table->integer('total_calories_kcal')->default(0);
            $table->timestamp('consumed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['user_id', 'telegram_update_id']);
            $table->index(['user_id', 'consumed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meals');
    }
};
