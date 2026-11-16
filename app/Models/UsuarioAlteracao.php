<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma alteração feita pela tela Usuários. Só é criada, nunca editada.
 */
class UsuarioAlteracao extends Model
{
    protected $table = 'usuario_alteracoes';

    /** @var list<string> */
    protected $fillable = ['usuario_id', 'feito_por_id', 'acao', 'mudancas', 'ip'];

    protected function casts(): array
    {
        return ['mudancas' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** @return BelongsTo<User, $this> */
    public function feitoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'feito_por_id');
    }

    public function rotulo(): string
    {
        return match ($this->acao) {
            'CRIACAO' => 'Login criado',
            'ALTERACAO' => 'Dados ou permissões alterados',
            'SENHA_RESETADA' => 'Senha resetada',
            default => $this->acao,
        };
    }
}
