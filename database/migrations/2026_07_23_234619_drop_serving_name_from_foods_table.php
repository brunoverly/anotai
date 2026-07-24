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
            // Nunca é lido em lugar nenhum do código (nem cálculo, nem view,
            // nem payload enviado pra Groq/Open Food Facts) — só serving_size_g
            // entra na matemática de conversão. Coluna morta, dado descritivo
            // que nunca teve consumidor.
            $table->dropColumn('serving_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            $table->string('serving_name')->default('grama')->after('calories_kcal');
        });
    }
};
