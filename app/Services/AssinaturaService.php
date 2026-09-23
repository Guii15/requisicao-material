<?php

namespace App\Services;

use App\Enums\EtapaAssinatura as Etapa;
use App\Enums\MetodoAssinatura;
use App\Enums\StatusRequisicao as S;
use App\Enums\TipoRequisicao;
use App\Models\Requisicao;
use App\Models\RequisicaoAssinatura;
use App\Models\RequisicaoItem;
use App\Models\User;

/**
 * Assinatura eletrônica simples (não é ICP-Brasil).
 *
 * Cada assinatura guarda o JSON canônico exatamente como foi assinado e o SHA-256 dele,
 * encadeado com o hash da assinatura anterior. A verificação detecta:
 * - conteúdo de assinatura alterado ou cadeia quebrada;
 * - dados da requisição alterados depois da última assinatura;
 * - status avançado sem a assinatura da etapa (ex.: pular a aprovação editando o banco).
 */
class AssinaturaService
{
    /**
     * Campo da requisição => etapa que obrigatoriamente tem assinatura da mesma pessoa.
     */
    private const RESPONSAVEL_POR_ETAPA = [
        'aprovado_por_id' => Etapa::APROVACAO_SETOR,
        'reprovado_por_id' => Etapa::REPROVACAO_SETOR,
        'separado_por_id' => Etapa::SEPARACAO,
        'liberado_por_id' => Etapa::LIBERACAO_ESTOQUE,
        'reprovado_estoque_por_id' => Etapa::REPROVACAO_ESTOQUE,
        'entregue_por_id' => Etapa::ENTREGA,
        'devolucao_conferida_por_id' => Etapa::DEVOLUCAO,
        'baixa_por_id' => Etapa::BAIXA,
    ];

    public function assinarComSenha(Requisicao $requisicao, Etapa $etapa, User $assinante): RequisicaoAssinatura
    {
        $anterior = RequisicaoAssinatura::query()
            ->where('requisicao_id', $requisicao->id)
            ->orderByDesc('id')
            ->value('hash_documento');

        $assinadoEm = now()->startOfSecond();

        $conteudo = self::json([
            'documento' => $this->dadosDoDocumento($requisicao),
            'etapa' => $etapa->value,
            'assinante' => [
                'user_id' => $assinante->id,
                'nome' => $assinante->nome,
                'cargo' => $assinante->cargo,
                'metodo' => MetodoAssinatura::SENHA->value,
            ],
            'assinado_em' => $assinadoEm->toIso8601String(),
            'hash_anterior' => $anterior,
        ]);

        return RequisicaoAssinatura::create([
            'requisicao_id' => $requisicao->id,
            'etapa' => $etapa,
            'user_id' => $assinante->id,
            'nome_assinante' => $assinante->nome,
            'cargo_assinante' => $assinante->cargo,
            'metodo' => MetodoAssinatura::SENHA,
            'conteudo_assinado' => $conteudo,
            'hash_documento' => hash('sha256', $conteudo),
            'hash_anterior' => $anterior,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'assinado_em' => $assinadoEm,
        ]);
    }

    public function verificar(Requisicao $requisicao): ResultadoVerificacao
    {
        $assinaturas = RequisicaoAssinatura::query()
            ->where('requisicao_id', $requisicao->id)
            ->orderBy('id')
            ->get();

        $problemas = [];
        $hashAnterior = null;

        foreach ($assinaturas as $assinatura) {
            $rotulo = $assinatura->etapa->rotulo();
            $conteudo = json_decode($assinatura->conteudo_assinado, true);

            if (! hash_equals($assinatura->hash_documento, hash('sha256', $assinatura->conteudo_assinado))) {
                $problemas[] = "{$rotulo}: o conteúdo assinado foi alterado.";
            }

            if ($assinatura->hash_anterior !== $hashAnterior || ($conteudo['hash_anterior'] ?? null) !== $hashAnterior) {
                $problemas[] = "{$rotulo}: a sequência de assinaturas foi quebrada.";
            }

            if (($conteudo['etapa'] ?? null) !== $assinatura->etapa->value
                || ($conteudo['assinante']['user_id'] ?? null) !== $assinatura->user_id
                || ($conteudo['assinante']['nome'] ?? null) !== $assinatura->nome_assinante
                || ($conteudo['documento']['numero'] ?? null) !== $requisicao->numero) {
                $problemas[] = "{$rotulo}: o registro da assinatura não confere com o conteúdo assinado.";
            }

            $hashAnterior = $assinatura->hash_documento;
        }

        $ultima = $assinaturas->last();

        if ($ultima === null) {
            $problemas[] = 'A requisição não tem nenhuma assinatura.';
        } else {
            // O status muda sem assinatura em algumas etapas (ex.: início da separação), então fica
            // fora da comparação. Status avançado sem a etapa assinada é pego logo abaixo.
            $assinado = json_decode($ultima->conteudo_assinado, true)['documento'] ?? [];
            $atual = $this->dadosDoDocumento($requisicao);
            unset($assinado['status'], $atual['status']);

            if (self::json($assinado) !== self::json($atual)) {
                $problemas[] = 'Os dados atuais da requisição não conferem com a última assinatura.';
            }
        }

        foreach ($this->etapasExigidasPeloStatus($requisicao) as $etapa) {
            if (! $assinaturas->contains(fn (RequisicaoAssinatura $a) => $a->etapa === $etapa)) {
                $problemas[] = "Falta a assinatura \"{$etapa->rotulo()}\" para o status atual.";
            }
        }

        foreach (self::RESPONSAVEL_POR_ETAPA as $campo => $etapa) {
            $responsavel = $requisicao->getAttribute($campo);

            if ($responsavel !== null && ! $assinaturas->contains(
                fn (RequisicaoAssinatura $a) => $a->etapa === $etapa && $a->user_id === $responsavel
            )) {
                $problemas[] = "O responsável registrado em \"{$etapa->rotulo()}\" não tem a assinatura dessa etapa.";
            }
        }

        return new ResultadoVerificacao($problemas === [], array_values(array_unique($problemas)));
    }

    /**
     * Código curto impresso no PDF: 12 primeiros caracteres do hash (ex.: A3F9-1C7E-88B2).
     */
    public static function codigoCurto(string $hash): string
    {
        return implode('-', str_split(strtoupper(substr($hash, 0, 12)), 4));
    }

    /**
     * O que é assinado da requisição. Setor e solicitante entram pelo id: renomear um setor
     * no cadastro não pode acusar divergência.
     *
     * @return array<string, mixed>
     */
    public function dadosDoDocumento(Requisicao $requisicao): array
    {
        return [
            'numero' => $requisicao->numero,
            'tipo' => $requisicao->tipo->value,
            'status' => $requisicao->status->value,
            'setor_id' => $requisicao->setor_id,
            'solicitante_id' => $requisicao->solicitante_id,
            'setor_destino_id' => $requisicao->setor_destino_id,
            'justificativa' => $requisicao->justificativa,
            'finalidade' => $requisicao->finalidade,
            'finalidade_complemento_estoque' => $requisicao->finalidade_complemento_estoque,
            'data_prevista_devolucao' => $requisicao->data_prevista_devolucao?->toDateString(),
            'baixa_documento_winthor' => $requisicao->baixa_documento_winthor,
            'itens' => $requisicao->itens()->get()->map(fn (RequisicaoItem $item) => [
                'id' => $item->id,
                'descricao' => $item->descricao,
                'unidade' => $item->unidade,
                'qtd_solicitada' => $item->qtd_solicitada,
                'qtd_separada' => $item->qtd_separada,
                'motivo_divergencia_separacao' => $item->motivo_divergencia_separacao,
                'qtd_devolvida_ok' => $item->qtd_devolvida_ok,
                'qtd_devolvida_defeito' => $item->qtd_devolvida_defeito,
                'qtd_nao_devolvida' => $item->qtd_nao_devolvida,
                'observacao_devolucao' => $item->observacao_devolucao,
            ])->all(),
        ];
    }

    /**
     * @return list<Etapa>
     */
    private function etapasExigidasPeloStatus(Requisicao $requisicao): array
    {
        $status = $requisicao->status;
        $exigidas = [Etapa::SOLICITACAO];

        $depoisDaSeparacao = [
            S::AGUARDANDO_LIBERACAO_ESTOQUE, S::LIBERADA, S::REPROVADA_ESTOQUE, S::PRONTA_PARA_RETIRADA,
            S::ENTREGUE, S::EM_POSSE, S::DEVOLVIDA, S::DEVOLUCAO_COM_PENDENCIA, S::AGUARDANDO_BAIXA, S::BAIXADA,
        ];
        $depoisDaEntrega = [S::ENTREGUE, S::EM_POSSE, S::DEVOLVIDA, S::DEVOLUCAO_COM_PENDENCIA, S::AGUARDANDO_BAIXA, S::BAIXADA];

        if ($status === S::APROVADA || $status === S::EM_SEPARACAO || in_array($status, $depoisDaSeparacao, true)) {
            $exigidas[] = Etapa::APROVACAO_SETOR;
        }

        if ($status === S::REPROVADA) {
            $exigidas[] = Etapa::REPROVACAO_SETOR;
        }

        if (in_array($status, $depoisDaSeparacao, true)) {
            $exigidas[] = Etapa::SEPARACAO;
        }

        if ($requisicao->tipo === TipoRequisicao::USO_CONSUMO
            && in_array($status, [S::LIBERADA, S::PRONTA_PARA_RETIRADA, S::ENTREGUE, S::AGUARDANDO_BAIXA, S::BAIXADA], true)) {
            $exigidas[] = Etapa::LIBERACAO_ESTOQUE;
        }

        if ($status === S::REPROVADA_ESTOQUE) {
            $exigidas[] = Etapa::REPROVACAO_ESTOQUE;
        }

        if (in_array($status, $depoisDaEntrega, true)) {
            $exigidas[] = Etapa::ENTREGA;
            $exigidas[] = Etapa::RETIRADA;
        }

        if ($status === S::DEVOLVIDA || $status === S::DEVOLUCAO_COM_PENDENCIA) {
            $exigidas[] = Etapa::DEVOLUCAO;
        }

        if ($status === S::BAIXADA) {
            $exigidas[] = Etapa::BAIXA;
        }

        return $exigidas;
    }

    /**
     * JSON canônico: chaves em ordem alfabética em todos os níveis, sem escapar acentos e barras.
     *
     * @param  array<array-key, mixed>  $dados
     */
    private static function json(array $dados): string
    {
        return json_encode(self::ordenar($dados), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<array-key, mixed>  $dados
     * @return array<array-key, mixed>
     */
    private static function ordenar(array $dados): array
    {
        if (! array_is_list($dados)) {
            ksort($dados, SORT_STRING);
        }

        foreach ($dados as $chave => $valor) {
            if (is_array($valor)) {
                $dados[$chave] = self::ordenar($valor);
            }
        }

        return $dados;
    }
}
