<?php

namespace Database\Factories;

use App\Models\Requisicao;
use App\Models\RequisicaoItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequisicaoItem>
 */
class RequisicaoItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requisicao_id' => Requisicao::factory(),
            'descricao' => fake()->words(3, true),
            'unidade' => 'UN',
            'qtd_solicitada' => fake()->numberBetween(1, 10),
        ];
    }
}
