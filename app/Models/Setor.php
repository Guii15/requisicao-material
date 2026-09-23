<?php

namespace App\Models;

use Database\Factories\SetorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Setor extends Model
{
    /** @use HasFactory<SetorFactory> */
    use HasFactory;

    protected $table = 'setores';

    /** @var list<string> */
    protected $fillable = [
        'nome',
        'ativo',
        'sem_sublider_definido',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'sem_sublider_definido' => 'boolean',
        ];
    }

    /** @return HasMany<User, $this> */
    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Quem aprova as requisições deste setor (pode ser gente de outro setor).
     *
     * @return BelongsToMany<User, $this, SetorAprovador, 'pivot'>
     */
    public function aprovadores(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'setor_aprovadores')
            ->using(SetorAprovador::class)
            ->withPivot('papel')
            ->withTimestamps();
    }
}
