<?php

namespace Database\Factories;

use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Requisicao>
 */
class RequisicaoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero' => fn () => sprintf('REQ-%d-%06d', now()->year, fake()->unique()->numberBetween(1, 999999)),
            'tipo' => TipoRequisicao::TESTE,
            'status' => StatusRequisicao::AGUARDANDO_APROVACAO,
            'solicitante_id' => User::factory(),
            'setor_id' => fn (array $atributos) => User::query()->whereKey($atributos['solicitante_id'])->value('setor_id'),
            'justificativa' => fake()->sentence(),
            'finalidade' => fake()->sentence(),
            'data_prevista_devolucao' => now()->addDay()->toDateString(),
        ];
    }

    public function usoConsumo(): static
    {
        return $this->state(fn () => [
            'tipo' => TipoRequisicao::USO_CONSUMO,
            'data_prevista_devolucao' => null,
        ]);
    }

    public function status(StatusRequisicao $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
