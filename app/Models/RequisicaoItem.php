<?php

namespace App\Models;

use App\Exceptions\RegistroProtegidoException;
use Database\Factories\RequisicaoItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisicaoItem extends Model
{
    /** @use HasFactory<RequisicaoItemFactory> */
    use HasFactory;

    protected $table = 'requisicao_itens';

    /**
     * Separação e devolução são gravadas pelo RequisicaoWorkflow.
     *
     * @var list<string>
     */
    protected $fillable = [
        'codigo',
        'descricao',
        'unidade',
        'qtd_solicitada',
    ];

    protected function casts(): array
    {
        return [
            'qtd_solicitada' => 'decimal:3',
            'qtd_separada' => 'decimal:3',
            'qtd_devolvida_ok' => 'decimal:3',
            'qtd_devolvida_defeito' => 'decimal:3',
            'qtd_nao_devolvida' => 'decimal:3',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            if (! Requisicao::emWorkflow()) {
                throw RegistroProtegidoException::foraDoWorkflow('Item da requisição');
            }
        });

        static::deleting(fn () => throw RegistroProtegidoException::exclusao('Item da requisição'));
    }

    /** @return BelongsTo<Requisicao, $this> */
    public function requisicao(): BelongsTo
    {
        return $this->belongsTo(Requisicao::class);
    }
}
