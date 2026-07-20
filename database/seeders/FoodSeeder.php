<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FoodSeeder extends Seeder
{
    public function run(): void
    {
        $alimentos = [
            // [Nome, P, C, G, Kcal por 100g/ml, Nome da Porção, Peso da Porção em gramas/ml]
            ['name' => 'banana', 'protein_g' => 1.11, 'carbohydrate_g' => 28.88, 'fat_g' => 0.11, 'calories_kcal' => 108, 'serving_name' => 'unidade', 'serving_size_g' => 90],
            ['name' => 'energetico zero', 'protein_g' => 0.00, 'carbohydrate_g' => 0.00, 'fat_g' => 0.00, 'calories_kcal' => 1, 'serving_name' => 'unidade', 'serving_size_g' => 270],
            ['name' => 'iogurte zero', 'protein_g' => 1.71, 'carbohydrate_g' => 1.31, 'fat_g' => 0.00, 'calories_kcal' => 13, 'serving_name' => 'unidade', 'serving_size_g' => 200],
            ['name' => 'leite desnatado', 'protein_g' => 3.10, 'carbohydrate_g' => 4.85, 'fat_g' => 0.00, 'calories_kcal' => 32, 'serving_name' => 'ml', 'serving_size_g' => 1],
            ['name' => 'whey protein', 'protein_g' => 80.00, 'carbohydrate_g' => 13.33, 'fat_g' => 1.00, 'calories_kcal' => 350, 'serving_name' => 'dose', 'serving_size_g' => 30],
            ['name' => 'aveia', 'protein_g' => 13.90, 'carbohydrate_g' => 56.60, 'fat_g' => 6.00, 'calories_kcal' => 340, 'serving_name' => 'colher', 'serving_size_g' => 15],
            ['name' => 'ovo', 'protein_g' => 13.00, 'carbohydrate_g' => 1.20, 'fat_g' => 10.60, 'calories_kcal' => 154, 'serving_name' => 'unidade', 'serving_size_g' => 50],
            ['name' => 'arroz', 'protein_g' => 2.40, 'carbohydrate_g' => 28.20, 'fat_g' => 0.20, 'calories_kcal' => 130, 'serving_name' => 'grama', 'serving_size_g' => 1],
            ['name' => 'feijao', 'protein_g' => 5.40, 'carbohydrate_g' => 13.60, 'fat_g' => 0.85, 'calories_kcal' => 76, 'serving_name' => 'grama', 'serving_size_g' => 1],
            ['name' => 'macarrao', 'protein_g' => 5.76, 'carbohydrate_g' => 30.60, 'fat_g' => 0.92, 'calories_kcal' => 157, 'serving_name' => 'grama', 'serving_size_g' => 1],
            ['name' => 'leite integral', 'protein_g' => 3.40, 'carbohydrate_g' => 4.80, 'fat_g' => 3.20, 'calories_kcal' => 64, 'serving_name' => 'ml', 'serving_size_g' => 1],
            ['name' => 'pao de forma', 'protein_g' => 9.60, 'carbohydrate_g' => 49.60, 'fat_g' => 1.40, 'calories_kcal' => 230, 'serving_name' => 'fatia', 'serving_size_g' => 25],
            ['name' => 'tomate', 'protein_g' => 0.80, 'carbohydrate_g' => 3.50, 'fat_g' => 0.20, 'calories_kcal' => 23, 'serving_name' => 'grama', 'serving_size_g' => 1],
            ['name' => 'alface', 'protein_g' => 1.76, 'carbohydrate_g' => 2.97, 'fat_g' => 0.90, 'calories_kcal' => 15, 'serving_name' => 'grama', 'serving_size_g' => 1],
            ['name' => 'lombo de porco', 'protein_g' => 32.90, 'carbohydrate_g' => 0.00, 'fat_g' => 13.90, 'calories_kcal' => 256, 'serving_name' => 'grama', 'serving_size_g' => 1],
            ['name' => 'abacaxi', 'protein_g' => 0.54, 'carbohydrate_g' => 12.64, 'fat_g' => 0.12, 'calories_kcal' => 48, 'serving_name' => 'fatia', 'serving_size_g' => 100],
            ['name' => 'maca', 'protein_g' => 0.26, 'carbohydrate_g' => 13.81, 'fat_g' => 0.17, 'calories_kcal' => 52, 'serving_name' => 'unidade', 'serving_size_g' => 100],
            ['name' => 'bife acebolado', 'protein_g' => 11.56, 'carbohydrate_g' => 1.14, 'fat_g' => 6.82, 'calories_kcal' => 200, 'serving_name' => 'grama', 'serving_size_g' => 1],
            ['name' => 'salsicha', 'protein_g' => 16.00, 'carbohydrate_g' => 5.00, 'fat_g' => 16.00, 'calories_kcal' => 250, 'serving_name' => 'unidade', 'serving_size_g' => 50],
            ['name' => 'snickers', 'protein_g' => 8.00, 'carbohydrate_g' => 54.00, 'fat_g' => 20.00, 'calories_kcal' => 434, 'serving_name' => 'unidade', 'serving_size_g' => 50],
            ['name' => 'iogurte natural', 'protein_g' => 5.29, 'carbohydrate_g' => 6.47, 'fat_g' => 0.17, 'calories_kcal' => 53, 'serving_name' => 'unidade', 'serving_size_g' => 170],
            ['name' => 'batata frita', 'protein_g' => 3.30, 'carbohydrate_g' => 82.13, 'fat_g' => 1.11, 'calories_kcal' => 111, 'serving_name' => 'grama', 'serving_size_g' => 1],
            ['name' => 'cenoura', 'protein_g' => 1.70, 'carbohydrate_g' => 8.40, 'fat_g' => 0.00, 'calories_kcal' => 39, 'serving_name' => 'grama', 'serving_size_g' => 1],
            ['name' => 'mortadela', 'protein_g' => 26.66, 'carbohydrate_g' => 1.66, 'fat_g' => 2.91, 'calories_kcal' => 137, 'serving_name' => 'fatia', 'serving_size_g' => 15],
            ['name' => 'mussarela', 'protein_g' => 55.33, 'carbohydrate_g' => 5.33, 'fat_g' => 35.33, 'calories_kcal' => 560, 'serving_name' => 'fatia', 'serving_size_g' => 15],
            ['name' => 'frango levissimo', 'protein_g' => 26.00, 'carbohydrate_g' => 0.00, 'fat_g' => 3.80, 'calories_kcal' => 138, 'serving_name' => 'unidade', 'serving_size_g' => 140],
            ['name' => 'laranja', 'protein_g' => 1.00, 'carbohydrate_g' => 11.71, 'fat_g' => 0.14, 'calories_kcal' => 51, 'serving_name' => 'unidade', 'serving_size_g' => 130],
            ['name' => 'morango', 'protein_g' => 0.60, 'carbohydrate_g' => 6.50, 'fat_g' => 0.30, 'calories_kcal' => 30, 'serving_name' => 'grama', 'serving_size_g' => 1],
            ['name' => 'manga', 'protein_g' => 0.38, 'carbohydrate_g' => 15.00, 'fat_g' => 0.25, 'calories_kcal' => 60, 'serving_name' => 'grama', 'serving_size_g' => 1],
            ['name' => 'feijao tropeiro', 'protein_g' => 8.06, 'carbohydrate_g' => 26.87, 'fat_g' => 10.36, 'calories_kcal' => 230, 'serving_name' => 'grama', 'serving_size_g' => 1],
            ['name' => 'carne moida', 'protein_g' => 25.54, 'carbohydrate_g' => 0.00, 'fat_g' => 17.67, 'calories_kcal' => 269, 'serving_name' => 'grama', 'serving_size_g' => 1],
            ['name' => 'apresuntado', 'protein_g' => 30.00, 'carbohydrate_g' => 7.33, 'fat_g' => 5.33, 'calories_kcal' => 193, 'serving_name' => 'fatia', 'serving_size_g' => 15],
            ['name' => 'salpicao', 'protein_g' => 14.07, 'carbohydrate_g' => 5.77, 'fat_g' => 8.77, 'calories_kcal' => 158, 'serving_name' => 'grama', 'serving_size_g' => 1],
            ['name' => 'beterraba cozida', 'protein_g' => 1.67, 'carbohydrate_g' => 9.90, 'fat_g' => 0.18, 'calories_kcal' => 40, 'serving_name' => 'grama', 'serving_size_g' => 1],
            ['name' => 'maionese', 'protein_g' => 0.83, 'carbohydrate_g' => 7.50, 'fat_g' => 40.83, 'calories_kcal' => 400, 'serving_name' => 'colher', 'serving_size_g' => 12],
            ['name' => 'melao', 'protein_g' => 0.80, 'carbohydrate_g' => 8.20, 'fat_g' => 0.20, 'calories_kcal' => 30, 'serving_name' => 'fatia', 'serving_size_g' => 100],
            ['name' => 'linguica assada', 'protein_g' => 19.00, 'carbohydrate_g' => 11.00, 'fat_g' => 23.00, 'calories_kcal' => 300, 'serving_name' => 'grama', 'serving_size_g' => 1],
            ['name' => 'figado acebolado', 'protein_g' => 29.08, 'carbohydrate_g' => 5.26, 'fat_g' => 5.26, 'calories_kcal' => 191, 'serving_name' => 'grama', 'serving_size_g' => 1],
            ['name' => 'musculo', 'protein_g' => 31.20, 'carbohydrate_g' => 0.00, 'fat_g' => 6.70, 'calories_kcal' => 194, 'serving_name' => 'grama', 'serving_size_g' => 1],
            ['name' => 'abobora', 'protein_g' => 1.00, 'carbohydrate_g' => 6.51, 'fat_g' => 0.10, 'calories_kcal' => 26, 'serving_name' => 'grama', 'serving_size_g' => 1],
            ['name' => 'batata cozida', 'protein_g' => 1.20, 'carbohydrate_g' => 12.00, 'fat_g' => 0.10, 'calories_kcal' => 52, 'serving_name' => 'grama', 'serving_size_g' => 1],
            ['name' => 'peito de frango', 'protein_g' => 31.00, 'carbohydrate_g' => 0.00, 'fat_g' => 3.60, 'calories_kcal' => 165, 'serving_name' => 'grama', 'serving_size_g' => 1],
        ];

        foreach ($alimentos as $alimento) {
            DB::table('foods')->updateOrInsert(
                ['name' => $alimento['name']],
                [
                    'protein_g' => $alimento['protein_g'],
                    'carbohydrate_g' => $alimento['carbohydrate_g'],
                    'fat_g' => $alimento['fat_g'],
                    'calories_kcal' => $alimento['calories_kcal'],
                    'serving_name' => $alimento['serving_name'],
                    'serving_size_g' => $alimento['serving_size_g'],
                    'source' => 'local',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
