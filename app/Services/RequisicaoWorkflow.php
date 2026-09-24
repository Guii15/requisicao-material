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
 * ação -> transação com lockForUpdate, onde transição e Policy são conferidas de novo com o
 * dado atualizado -> altera -> assina -> registra evento.
 *
 * A senha de login já identifica quem está agindo — decisão de 24/09/2026: nenhuma etapa
 * pede senha de novo (só a assinatura por desenho da retirada, que é de quem não loga).
 * Os parâmetros $senha que ainda aparecem nas assinaturas dos métodos ficam por enquanto,
 * sem uso, pela compatibilidade de quem já chama passando — dá pra tirar numa limpeza depois.
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
    /**
     * $senha não é mais conferida (decisão de 24/09/2026: só a senha do login, sem repetir
     * a cada ação) — o parâmetro fica pela compatibilidade de quem ainda chama passando ela.
     */
    public function criar(User $solicitante, array $dados, ?string $senha = null): Requisicao
    {
        Gate::forUser($solicitante)->authorize('create', Requisicao::class);
        $validado = $this->validarAbertura($dados);

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

    /**
     * @param  array<int, mixed>  $itens  quantidade separada por id de item
     */
    public function separar(Requisicao $requisicao, User $por, array $itens, ?string $senha): Requisicao
    {
        return $this->executar(
            $requisicao, $por, StatusRequisicao::EM_SEPARACAO, 'separar', AcaoEvento::SEPARACAO,
            EtapaAssinatura::SEPARACAO, $senha,
            alterar: fn (Requisicao $r, array $dados) => $this->gravarSeparacao($r, $por, $dados['itens']),
            validar: fn () => ['itens' => $this->validarSeparacao($requisicao, $itens)],
        );
    }

    /**
     * @param  array<int, mixed>  $itens
     * @return array<int, string>
     */
    private function validarSeparacao(Requisicao $requisicao, array $itens): array
    {
        $regras = [];
        $nomes = [];
        $normalizado = [];

        foreach ($requisicao->itens as $item) {
            $bruto = $itens[$item->id] ?? null;
            $normalizado[$item->id] = is_string($bruto) ? str_replace(',', '.', trim($bruto)) : $bruto;
            // Não separa mais do que foi pedido: quem digitar mais que qtd_solicitada está com o número errado.
            $regras["itens.{$item->id}"] = ['required', 'numeric', 'min:0', 'max:'.$item->qtd_solicitada];
            $nomes["itens.{$item->id}"] = "quantidade separada de \"{$item->descricao}\"";
        }

        return Validator::make(['itens' => $normalizado], $regras, [], $nomes)->validate()['itens'];
    }

    /**
     * @param  array<int, string>  $quantidades
     */
    private function gravarSeparacao(Requisicao $requisicao, User $por, array $quantidades): void
    {
        foreach ($requisicao->itens as $item) {
            $item->forceFill(['qtd_separada' => $quantidades[$item->id]])->save();
        }

        $requisicao->forceFill(['separado_por_id' => $por->id, 'separado_em' => now()]);
    }

    /**
     * Liberação do estoque: só existe para Uso e Consumo. Avança sozinho pra pronta pra
     * retirada, igual a separação avança pra liberação.
     */
    public function liberar(Requisicao $requisicao, User $por, ?string $senha): Requisicao
    {
        return $this->executar(
            $requisicao, $por, StatusRequisicao::LIBERADA, 'liberar', AcaoEvento::LIBERACAO,
            EtapaAssinatura::LIBERACAO_ESTOQUE, $senha,
            alterar: fn (Requisicao $r) => $r->forceFill(['liberado_por_id' => $por->id, 'liberado_em' => now()]),
        );
    }

    public function reprovarEstoque(Requisicao $requisicao, User $por, ?string $motivo, ?string $senha): Requisicao
    {
        return $this->executar(
            $requisicao, $por, StatusRequisicao::REPROVADA_ESTOQUE, 'reprovarEstoque', AcaoEvento::REPROVACAO_ESTOQUE,
            EtapaAssinatura::REPROVACAO_ESTOQUE, $senha,
            alterar: fn (Requisicao $r, array $dados) => $r->forceFill([
                'reprovado_estoque_por_id' => $por->id,
                'reprovado_estoque_em' => now(),
                'motivo_reprovacao_estoque' => $dados['motivo'],
            ]),
            validar: fn () => ['motivo' => $this->exigirMotivo($motivo, 'Informe o motivo da reprovação do estoque')],
        );
    }

    /**
     * Entrega + retirada num passo só: o estoquista assina a entrega por senha, e quem retira
     * assina por desenho (não precisa de login — pode ser qualquer pessoa buscando o material).
     * As duas assinaturas ficam encadeadas, na ordem: entrega, depois retirada.
     */
    public function entregar(Requisicao $requisicao, User $por, string $retiradoPorNome, string $assinaturaDesenho, ?string $senha = null): Requisicao
    {
        $nome = $this->exigirMotivo($retiradoPorNome, 'Informe quem está retirando', minimo: 2, campo: 'retirado_por_nome');

        return $this->executar(
            $requisicao, $por, StatusRequisicao::ENTREGUE, 'entregar', AcaoEvento::ENTREGA, null, null,
            alterar: function (Requisicao $r) use ($por, $nome, $assinaturaDesenho) {
                $r->forceFill(['entregue_por_id' => $por->id, 'entregue_em' => now(), 'retirado_por_nome' => $nome]);
                $this->assinaturas->assinarComSenha($r, EtapaAssinatura::ENTREGA, $por);
                $this->assinaturas->assinarComDesenho($r, EtapaAssinatura::RETIRADA, $nome, $assinaturaDesenho);
            },
        );
    }

    /**
     * Só existe no Teste: registro de que o material chegou às mãos de quem pediu. Não muda
     * o status (a requisição já está Em posse desde a entrega) — só grava a assinatura.
     */
    public function confirmarRecebimento(Requisicao $requisicao, User $por, ?string $senha = null): Requisicao
    {
        Gate::forUser($por)->authorize('confirmarRecebimento', $requisicao);

        return DB::transaction(function () use ($requisicao, $por) {
            $atual = Requisicao::query()->whereKey($requisicao->getKey())->lockForUpdate()->firstOrFail();
            Gate::forUser($por)->authorize('confirmarRecebimento', $atual);

            Requisicao::viaWorkflow(fn () => $atual->forceFill(['recebido_em' => now()])->save());
            $this->assinaturas->assinarComSenha($atual, EtapaAssinatura::RECEBIMENTO, $por);
            $this->registrarEvento($atual, $por, AcaoEvento::RECEBIMENTO, $atual->status, $atual->status);

            return $atual;
        });
    }

    /**
     * Devolução (Teste): confere o que voltou item a item. Fecha "Devolvida" se tudo voltou
     * bom; qualquer defeito ou falta vira "Devolução com pendência".
     *
     * @param  array<int, array{ok?: mixed, defeito?: mixed, nao_devolvida?: mixed, observacao?: mixed}>  $itens
     */
    public function devolver(Requisicao $requisicao, User $por, array $itens, ?string $senha): Requisicao
    {
        $validado = $this->validarDevolucao($requisicao, $itens);
        $comPendencia = collect($validado)->contains(fn (array $linha) => bccomp($linha['defeito'], '0', 3) > 0 || bccomp($linha['nao_devolvida'], '0', 3) > 0);
        $para = $comPendencia ? StatusRequisicao::DEVOLUCAO_COM_PENDENCIA : StatusRequisicao::DEVOLVIDA;

        return $this->executar(
            $requisicao, $por, $para, 'devolver', AcaoEvento::DEVOLUCAO,
            EtapaAssinatura::DEVOLUCAO, $senha,
            alterar: fn (Requisicao $r, array $dados) => $this->gravarDevolucao($r, $por, $dados['itens']),
            validar: fn () => ['itens' => $validado],
        );
    }

    /**
     * @param  array<int, mixed>  $itens
     * @return array<int, array{ok: string, defeito: string, nao_devolvida: string, observacao: ?string}>
     */
    private function validarDevolucao(Requisicao $requisicao, array $itens): array
    {
        $norm = fn (mixed $v) => is_string($v) ? str_replace(',', '.', trim($v)) : ($v ?? '0');
        $regras = [];
        $nomes = [];
        $normalizado = [];

        foreach ($requisicao->itens as $item) {
            $linha = $itens[$item->id] ?? [];
            $normalizado[$item->id] = [
                'ok' => $norm($linha['ok'] ?? null),
                'defeito' => $norm($linha['defeito'] ?? null),
                'nao_devolvida' => $norm($linha['nao_devolvida'] ?? null),
                'observacao' => is_string($linha['observacao'] ?? null) && trim($linha['observacao']) !== '' ? trim($linha['observacao']) : null,
            ];

            foreach (['ok', 'defeito', 'nao_devolvida'] as $campo) {
                $regras["itens.{$item->id}.{$campo}"] = ['required', 'numeric', 'min:0'];
                $nomes["itens.{$item->id}.{$campo}"] = "quantidade \"{$campo}\" de \"{$item->descricao}\"";
            }
            $regras["itens.{$item->id}.observacao"] = ['nullable', 'string', 'max:1000'];
        }

        $validado = Validator::make(['itens' => $normalizado], $regras, [], $nomes)->validate()['itens'];

        foreach ($requisicao->itens as $item) {
            $linha = $validado[$item->id];
            $soma = bcadd(bcadd($linha['ok'], $linha['defeito'], 3), $linha['nao_devolvida'], 3);

            if (bccomp($soma, (string) $item->qtd_solicitada, 3) !== 0) {
                throw ValidationException::withMessages([
                    "itens.{$item->id}.ok" => "\"{$item->descricao}\": boa + defeito + não devolvida tem que somar {$item->qtd_solicitada}, e deu {$soma}.",
                ]);
            }
        }

        return $validado;
    }

    /**
     * @param  array<int, array{ok: string, defeito: string, nao_devolvida: string, observacao: ?string}>  $itens
     */
    private function gravarDevolucao(Requisicao $requisicao, User $por, array $itens): void
    {
        foreach ($requisicao->itens as $item) {
            $linha = $itens[$item->id];
            $item->forceFill([
                'qtd_devolvida_ok' => $linha['ok'],
                'qtd_devolvida_defeito' => $linha['defeito'],
                'qtd_nao_devolvida' => $linha['nao_devolvida'],
                'observacao_devolucao' => $linha['observacao'],
            ])->save();
        }

        $requisicao->forceFill(['devolucao_conferida_por_id' => $por->id, 'devolucao_conferida_em' => now()]);
    }

    /**
     * Baixa (Uso e Consumo): fecha a requisição com o documento do WinThor.
     */
    public function darBaixa(Requisicao $requisicao, User $por, string $documentoWinthor, ?string $observacao, ?string $senha): Requisicao
    {
        return $this->executar(
            $requisicao, $por, StatusRequisicao::BAIXADA, 'darBaixa', AcaoEvento::BAIXA,
            EtapaAssinatura::BAIXA, $senha,
            alterar: fn (Requisicao $r, array $dados) => $r->forceFill([
                'baixa_por_id' => $por->id,
                'baixa_em' => now(),
                'baixa_documento_winthor' => $dados['documento'],
                'baixa_observacao' => $dados['observacao'],
            ]),
            validar: fn () => [
                'documento' => $this->exigirMotivo($documentoWinthor, 'Informe o documento de baixa do WinThor', minimo: 2, campo: 'documento'),
                'observacao' => is_string($observacao) && trim($observacao) !== '' ? trim($observacao) : null,
            ],
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

            // Status de passagem (EM_SEPARACAO, LIBERADA, ENTREGUE) ficam no histórico e seguem na hora.
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

    private function exigirMotivo(?string $motivo, string $mensagem, int $minimo = 5, string $campo = 'motivo'): string
    {
        $motivo = trim((string) $motivo);

        if (mb_strlen($motivo) < $minimo) {
            throw ValidationException::withMessages([$campo => "{$mensagem} (pelo menos {$minimo} caracteres)."]);
        }

        if (mb_strlen($motivo) > 1000) {
            throw ValidationException::withMessages([$campo => "{$mensagem} (no máximo 1000 caracteres)."]);
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
