<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Papéis, situação e troca de senha ficam fora: só o admin altera, campo a campo.
     *
     * @var list<string>
     */
    protected $fillable = [
        'matricula',
        'nome',
        'login',
        'cargo',
        'setor_id',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'ativo' => 'boolean',
            'deve_trocar_senha' => 'boolean',
            'is_estoque' => 'boolean',
            'is_lider_estoque' => 'boolean',
            'is_responsavel_baixa' => 'boolean',
            'is_admin' => 'boolean',
        ];
    }

    /** @return BelongsTo<Setor, $this> */
    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class);
    }

    /**
     * Setores cujas requisições este usuário aprova.
     *
     * @return BelongsToMany<Setor, $this, SetorAprovador, 'pivot'>
     */
    public function setoresAprovados(): BelongsToMany
    {
        return $this->belongsToMany(Setor::class, 'setor_aprovadores')
            ->using(SetorAprovador::class)
            ->withPivot('papel')
            ->withTimestamps();
    }

    public function aprovaSetor(Setor|int $setor): bool
    {
        $setorId = $setor instanceof Setor ? $setor->id : $setor;

        return $this->setoresAprovados()->whereKey($setorId)->exists();
    }
}
