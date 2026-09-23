<?php

namespace App\Models;

use App\Enums\EtapaAssinatura;
use App\Enums\MetodoAssinatura;
use App\Exceptions\RegistroProtegidoException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Assinatura de uma etapa. Append-only: nunca é editada nem apagada.
 */
class RequisicaoAssinatura extends Model
{
    public $timestamps = false;

    protected $table = 'requisicao_assinaturas';

    /** @var list<string> */
    protected $fillable = [
        'requisicao_id',
        'etapa',
        'user_id',
        'nome_assinante',
        'cargo_assinante',
        'metodo',
        'imagem_path',
        'conteudo_assinado',
        'hash_documento',
        'hash_anterior',
        'ip',
        'user_agent',
        'assinado_em',
    ];

    protected function casts(): array
    {
        return [
            'etapa' => EtapaAssinatura::class,
            'metodo' => MetodoAssinatura::class,
            'assinado_em' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw RegistroProtegidoException::imutavel('Assinatura'));
        static::deleting(fn () => throw RegistroProtegidoException::imutavel('Assinatura'));
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
