<?php

namespace Database\Factories;

use App\Models\Setor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setor>
 */
class SetorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => 'SETOR '.fake()->unique()->numberBetween(1, 99999),
            'ativo' => true,
            'sem_sublider_definido' => false,
        ];
    }
}
