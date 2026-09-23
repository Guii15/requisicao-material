<?php

namespace App\Models;

use App\Enums\StatusRequisicao;
use App\Exceptions\RegistroProtegidoException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trilha de auditoria da requisição. Append-only: nunca é editada nem apagada.
 */
class RequisicaoEvento extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'requisicao_eventos';

    /** @var list<string> */
    protected $fillable = [
        'requisicao_id',
        'user_id',
        'acao',
        'status_de',
        'status_para',
        'dados',
        'ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'status_de' => StatusRequisicao::class,
            'status_para' => StatusRequisicao::class,
            'dados' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw RegistroProtegidoException::imutavel('Evento da requisição'));
        static::deleting(fn () => throw RegistroProtegidoException::imutavel('Evento da requisição'));
    }

    /** @return BelongsTo<Requisicao, $this> */
    public function requisicao(): BelongsTo
    {
        return $this->belongsTo(Requisicao::class);
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
