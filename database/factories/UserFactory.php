<?php

namespace Database\Factories;

use App\Enums\PapelSetor;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'matricula' => fake()->unique()->numberBetween(1, 999999),
            'nome' => fake()->name(),
            'login' => fake()->unique()->userName(),
            'cargo' => 'Auxiliar Administrativo',
            'setor_id' => Setor::factory(),
            'password' => static::$password ??= Hash::make('password'),
            'ativo' => true,
            'deve_trocar_senha' => false,
            'is_estoque' => false,
            'is_lider_estoque' => false,
            'is_responsavel_baixa' => false,
            'aprova_compras' => false,
            'is_admin' => false,
            'remember_token' => Str::random(10),
        ];
    }

    public function estoque(): static
    {
        return $this->state(fn () => ['is_estoque' => true]);
    }

    public function liderEstoque(): static
    {
        return $this->state(fn () => ['is_estoque' => true, 'is_lider_estoque' => true]);
    }

    public function responsavelBaixa(): static
    {
        return $this->state(fn () => ['is_responsavel_baixa' => true]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['is_admin' => true]);
    }

    public function inativo(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }

    public function aprovadorDe(Setor $setor, PapelSetor $papel = PapelSetor::LIDER): static
    {
        return $this->afterCreating(fn (User $user) => $user->setoresAprovados()->attach($setor->id, ['papel' => $papel]));
    }
}
