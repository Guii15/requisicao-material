<?php

namespace App\Models;

use App\Enums\EtapaAssinatura;
use App\Exceptions\RegistroProtegidoException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Assinatura recusada por senha errada. Append-only.
 */
class TentativaAssinatura extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'tentativas_assinatura';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'requisicao_id',
        'etapa',
        'bloqueou',
        'ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'etapa' => EtapaAssinatura::class,
            'bloqueou' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw RegistroProtegidoException::imutavel('Tentativa de assinatura'));
        static::deleting(fn () => throw RegistroProtegidoException::imutavel('Tentativa de assinatura'));
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
