<?php

namespace App\Services;

use App\Enums\AcaoEvento;
use App\Enums\EtapaAssinatura;
use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Parametro;
use App\Models\Requisicao;
use App\Models\RequisicaoEvento;
use App\Models\User;
use App\Support\DiasUteis;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Único lugar que muda o status de uma requisição.
 *
 * Toda ação segue a mesma sequência: transição permitida na tabela -> Policy -> dados da
 * ação -> senha (quando a etapa assina) -> transação com lockForUpdate, onde transição e
 * Policy são conferidas de novo com o dado atualizado -> altera -> assina -> registra evento.
 */
class RequisicaoWorkflow
{
    public function __construct(
        private readonly AssinaturaService $assinaturas,
        private readonly ConfirmacaoSenha $senhas,
        private readonly NumeracaoRequisicao $numeracao,
    ) {}

    /**
     * @param  array<string, mixed>  $dados  tipo, itens (descricao, unidade, quantidade), justificativa, finalidade, data_prevista_devolucao
     */
    public function criar(User $solicitante, array $dados, ?string $senha): Requisicao
    {
        Gate::forUser($solicitante)->authorize('create', Requisicao::class);
        $validado = $this->validarAbertura($dados);
        $this->senhas->confirmar($solicitante, $senha, null, EtapaAssinatura::SOLICITACAO);

        return DB::transaction(function () use ($solicitante, $validado) {
            $tipo = TipoRequisicao::from($validado['tipo']);

            $requisicao = new Requisicao([
                'tipo' => $tipo,
                'justificativa' => $validado['justificativa'],
                'finalidade' => $validado['finalidade'],
                'data_prevista_devolucao' => $tipo->exigeDevolucao() ? $validado['data_prevista_devolucao'] : null,
            ]);

            $requisicao->forceFill([
                'numero' => $this->numeracao->proximo(),
                'status' => StatusRequisicao::AGUARDANDO_APROVACAO,
                'status_alterado_em' => now(),
                'solicitante_id' => $solicitante->id,
                'setor_id' => $solicitante->setor_id,
            ])->save();

            foreach ($validado['itens'] as $item) {
                $requisicao->itens()->create([
                    'descricao' => $item['descricao'],
                    'unidade' => $item['unidade'],
                    'qtd_solicitada' => $item['quantidade'],
                ]);
            }

            $this->assinaturas->assinarComSenha($requisicao, EtapaAssinatura::SOLICITACAO, $solicitante);
            $this->registrarEvento($requisicao, $solicitante, AcaoEvento::CRIACAO, null, $requisicao->status, [
                'itens' => count($validado['itens']),
            ]);

            return $requisicao;
        });
    }

    public function aprovar(Requisicao $requisicao, User $por, ?string $senha): Requisicao
    {
        return $this->executar(
            $requisicao, $por, StatusRequisicao::APROVADA, 'aprovar', AcaoEvento::APROVACAO,
            EtapaAssinatura::APROVACAO_SETOR, $senha,
            alterar: fn (Requisicao $r) => $r->forceFill(['aprovado_por_id' => $por->id, 'aprovado_em' => now()]),
        );
    }

    public function reprovar(Requisicao $requisicao, User $por, ?string $motivo, ?string $senha): Requisicao
    {
        return $this->executar(
            $requisicao, $por, StatusRequisicao::REPROVADA, 'reprovar', AcaoEvento::REPROVACAO,
            EtapaAssinatura::REPROVACAO_SETOR, $senha,
            alterar: fn (Requisicao $r, array $dados) => $r->forceFill([
                'reprovado_por_id' => $por->id,
                'reprovado_em' => now(),
                'motivo_reprovacao' => $dados['motivo'],
            ]),
            validar: fn () => ['motivo' => $this->exigirMotivo($motivo, 'Informe o motivo da reprovação')],
        );
    }

    public function cancelar(Requisicao $requisicao, User $por, ?string $motivo): Requisicao
    {
        return $this->executar(
            $requisicao, $por, StatusRequisicao::CANCELADA, 'cancelar', AcaoEvento::CANCELAMENTO,
            null, null,
            alterar: fn (Requisicao $r, array $dados) => $r->forceFill([
                'cancelado_por_id' => $por->id,
                'cancelado_em' => now(),
                'motivo_cancelamento' => $dados['motivo'],
            ]),
            validar: fn () => ['motivo' => $this->exigirMotivo($motivo, 'Informe o motivo do cancelamento')],
        );
    }

    /**
     * @param  Closure(Requisicao, array<string, mixed>): mixed  $alterar
     * @param  (Closure(): array<string, mixed>)|null  $validar
     */
    private function executar(
        Requisicao $requisicao,
        User $por,
        StatusRequisicao $para,
        string $habilidade,
        AcaoEvento $acao,
        ?EtapaAssinatura $etapa,
        ?string $senha,
        Closure $alterar,
        ?Closure $validar = null,
    ): Requisicao {
        $this->garantirTransicao($requisicao, $para);
        Gate::forUser($por)->authorize($habilidade, $requisicao);
        $dados = $validar !== null ? $validar() : [];

        if ($etapa !== null) {
            $this->senhas->confirmar($por, $senha, $requisicao, $etapa);
        }

        $atual = DB::transaction(function () use ($requisicao, $por, $para, $habilidade, $acao, $etapa, $alterar, $dados) {
            // Dois aprovadores ao mesmo tempo: o segundo espera o lock e cai na conferência abaixo.
            $atual = Requisicao::query()->whereKey($requisicao->getKey())->lockForUpdate()->firstOrFail();
            $this->garantirTransicao($atual, $para);
            Gate::forUser($por)->authorize($habilidade, $atual);
            $de = $atual->status;

            Requisicao::viaWorkflow(function () use ($atual, $alterar, $dados, $para) {
                $alterar($atual, $dados);
                $atual->forceFill(['status' => $para, 'status_alterado_em' => now()])->save();
            });

            if ($etapa !== null) {
                $this->assinaturas->assinarComSenha($atual, $etapa, $por);
            }

            $this->registrarEvento($atual, $por, $acao, $de, $para, $dados);

            // Status de passagem (LIBERADA, ENTREGUE) ficam no histórico e seguem na hora.
            $proximo = $para->proximoAutomatico($atual->tipo);

            if ($proximo !== null) {
                Requisicao::viaWorkflow(fn () => $atual->forceFill(['status' => $proximo, 'status_alterado_em' => now()])->save());
                $this->registrarEvento($atual, $por, AcaoEvento::AVANCO_AUTOMATICO, $para, $proximo);
            }

            return $atual;
        });

        $requisicao->setRawAttributes($atual->getAttributes(), true);

        return $atual;
    }

    private function garantirTransicao(Requisicao $requisicao, StatusRequisicao $para): void
    {
        if (! $requisicao->status->podeIrPara($para, $requisicao->tipo)) {
            throw RegraDeNegocioException::statusMudou($requisicao);
        }
    }

    private function exigirMotivo(?string $motivo, string $mensagem): string
    {
        $motivo = trim((string) $motivo);

        if (mb_strlen($motivo) < 5) {
            throw ValidationException::withMessages(['motivo' => "{$mensagem} (pelo menos 5 caracteres)."]);
        }

        if (mb_strlen($motivo) > 1000) {
            throw ValidationException::withMessages(['motivo' => 'O motivo pode ter no máximo 1000 caracteres.']);
        }

        return $motivo;
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function registrarEvento(
        Requisicao $requisicao,
        User $por,
        AcaoEvento $acao,
        ?StatusRequisicao $de,
        ?StatusRequisicao $para,
        array $dados = [],
    ): void {
        RequisicaoEvento::create([
            'requisicao_id' => $requisicao->id,
            'user_id' => $por->id,
            'acao' => $acao,
            'status_de' => $de,
            'status_para' => $para,
            'dados' => $dados === [] ? null : $dados,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function validarAbertura(array $dados): array
    {
        $texto = fn (mixed $valor) => is_string($valor) ? trim($valor) : $valor;

        $dados['justificativa'] = $texto($dados['justificativa'] ?? null);
        $dados['finalidade'] = $texto($dados['finalidade'] ?? null);

        if (is_array($dados['itens'] ?? null)) {
            $dados['itens'] = array_map(fn (mixed $item) => ! is_array($item) ? $item : [
                'descricao' => $texto($item['descricao'] ?? null),
                'unidade' => is_string($item['unidade'] ?? null) ? mb_strtoupper(trim($item['unidade'])) : ($item['unidade'] ?? null),
                // Aceita vírgula decimal (2,5).
                'quantidade' => is_string($item['quantidade'] ?? null) ? str_replace(',', '.', trim($item['quantidade'])) : ($item['quantidade'] ?? null),
            ], $dados['itens']);
        }

        $prazo = Parametro::inteiro('prazo_max_devolucao_dias');
        $limite = DiasUteis::doBanco()->somar(now(), $prazo);

        return Validator::make($dados, [
            'tipo' => ['required', Rule::enum(TipoRequisicao::class)],
            'itens' => ['required', 'array', 'min:1', 'max:50'],
            'itens.*' => ['array'],
            'itens.*.descricao' => ['required', 'string', 'max:255'],
            'itens.*.unidade' => ['required', 'string', 'max:20'],
            'itens.*.quantidade' => ['required', 'numeric', 'gt:0', 'max:999999.999', 'decimal:0,3'],
            'justificativa' => ['required', 'string', 'min:5', 'max:2000'],
            'finalidade' => ['required', 'string', 'min:5', 'max:2000'],
            'data_prevista_devolucao' => [
                'exclude_unless:tipo,'.TipoRequisicao::TESTE->value,
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today',
                'before_or_equal:'.$limite->toDateString(),
            ],
        ], [
            'tipo.required' => 'Escolha o tipo da requisição.',
            'itens.required' => 'Inclua pelo menos um item.',
            'itens.min' => 'Inclua pelo menos um item.',
            'itens.max' => 'Uma requisição pode ter no máximo 50 itens.',
            'itens.*.quantidade.gt' => 'A quantidade precisa ser maior que zero.',
            'itens.*.quantidade.decimal' => 'Use no máximo 3 casas decimais.',
            'data_prevista_devolucao.required' => 'Informe a data prevista de devolução.',
            'data_prevista_devolucao.after_or_equal' => 'A devolução não pode ser antes de hoje.',
            'data_prevista_devolucao.before_or_equal' => "A devolução pode ser no máximo até {$limite->format('d/m/Y')} ({$prazo} dias úteis a partir da solicitação).",
        ], [
            'tipo' => 'tipo',
            'itens.*.descricao' => 'descrição do item',
            'itens.*.unidade' => 'unidade',
            'itens.*.quantidade' => 'quantidade',
            'justificativa' => 'justificativa',
            'finalidade' => 'finalidade',
            'data_prevista_devolucao' => 'data prevista de devolução',
        ])->validate();
    }
}
