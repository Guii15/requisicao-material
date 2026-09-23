<?php

namespace App\Exceptions;

use App\Models\Requisicao;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Regra de negócio violada por uma ação do usuário (ex.: aprovar o que outra pessoa já aprovou).
 * Na tela vira aviso de erro; em chamada JSON, HTTP 422.
 */
class RegraDeNegocioException extends RuntimeException
{
    /**
     * A requisição não está mais no status que a ação espera. Diz quem mudou e quando.
     */
    public static function statusMudou(Requisicao $requisicao): self
    {
        $ultimoEvento = $requisicao->eventos()->with('usuario')->reorder('id', 'desc')->first();
        $detalhe = '';

        if ($ultimoEvento !== null) {
            $quando = $ultimoEvento->created_at;
            $data = $quando->isToday()
                ? 'às '.$quando->format('H:i')
                : 'em '.$quando->format('d/m/Y').' às '.$quando->format('H:i');
            $por = $ultimoEvento->usuario !== null ? "por {$ultimoEvento->usuario->nome} " : '';
            $detalhe = " ({$por}{$data})";
        }

        return new self(
            "A requisição {$requisicao->numero} já está como \"{$requisicao->status->rotulo()}\"{$detalhe}. Atualize a página."
        );
    }

    public function render(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->with('erro', $this->getMessage());
    }
}
