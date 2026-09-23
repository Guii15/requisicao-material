<?php

namespace App\Models;

use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
use App\Exceptions\RegistroProtegidoException;
use Closure;
use Database\Factories\RequisicaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Requisicao extends Model
{
    /** @use HasFactory<RequisicaoFactory> */
    use HasFactory;

    protected $table = 'requisicoes';

    /**
     * Só o que o solicitante informa. Status, responsáveis, datas de etapa e assinatura
     * nunca entram aqui: mudam apenas pelo RequisicaoWorkflow.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tipo',
        'justificativa',
        'finalidade',
        'data_prevista_devolucao',
    ];

    private static int $alteracoesAutorizadas = 0;

    protected function casts(): array
    {
        return [
            'tipo' => TipoRequisicao::class,
            'status' => StatusRequisicao::class,
            'data_prevista_devolucao' => 'date',
            'aprovado_em' => 'datetime',
            'separado_em' => 'datetime',
            'liberado_em' => 'datetime',
            'entregue_em' => 'datetime',
            'recebido_em' => 'datetime',
            'devolucao_conferida_em' => 'datetime',
            'baixa_em' => 'datetime',
            'cancelado_em' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            if (! self::emWorkflow()) {
                throw RegistroProtegidoException::foraDoWorkflow('Requisição');
            }
        });

        static::deleting(fn () => throw RegistroProtegidoException::exclusao('Requisição'));
    }

    /**
     * Libera a alteração de requisições e itens enquanto $alteracao executa.
     * Uso exclusivo do RequisicaoWorkflow.
     */
    public static function viaWorkflow(Closure $alteracao): mixed
    {
        self::$alteracoesAutorizadas++;

        try {
            return $alteracao();
        } finally {
            self::$alteracoesAutorizadas--;
        }
    }

    public static function emWorkflow(): bool
    {
        return self::$alteracoesAutorizadas > 0;
    }

    /** @return HasMany<RequisicaoItem, $this> */
    public function itens(): HasMany
    {
        return $this->hasMany(RequisicaoItem::class)->orderBy('id');
    }

    /** @return HasMany<RequisicaoAssinatura, $this> */
    public function assinaturas(): HasMany
    {
        return $this->hasMany(RequisicaoAssinatura::class)->orderBy('id');
    }

    /** @return HasMany<RequisicaoEvento, $this> */
    public function eventos(): HasMany
    {
        return $this->hasMany(RequisicaoEvento::class)->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_id');
    }

    /** @return BelongsTo<Setor, $this> */
    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class);
    }

    /** @return BelongsTo<Setor, $this> */
    public function setorDestino(): BelongsTo
    {
        return $this->belongsTo(Setor::class, 'setor_destino_id');
    }

    /** @return BelongsTo<User, $this> */
    public function aprovadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprovado_por_id');
    }

    /** @return BelongsTo<User, $this> */
    public function separadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'separado_por_id');
    }

    /** @return BelongsTo<User, $this> */
    public function liberadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'liberado_por_id');
    }

    /** @return BelongsTo<User, $this> */
    public function entreguePor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entregue_por_id');
    }

    /** @return BelongsTo<User, $this> */
    public function retiradoPorUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retirado_por_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function devolucaoConferidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'devolucao_conferida_por_id');
    }

    /** @return BelongsTo<User, $this> */
    public function baixaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'baixa_por_id');
    }

    /** @return BelongsTo<User, $this> */
    public function canceladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelado_por_id');
    }
}
