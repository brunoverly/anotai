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
        Schema::table('foods', function (Blueprint $table) {
            // Nome da unidade de consumo comum (ex: 'unidade', 'fatia', 'dose', 'grama')
            $table->string('serving_name')->default('grama')->after('calories_kcal');
            
            // Peso equivalente em gramas/ml dessa porção padrão (ex: 25 para fatia de pão, 50 para o bombom)
            $table->integer('serving_size_g')->default(1)->after('serving_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            $table->dropColumn(['serving_name', 'serving_size_g']);
        });
    }
};
