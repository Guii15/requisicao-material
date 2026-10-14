<?php

namespace App\Services;

use App\Enums\AcaoEvento;
use App\Enums\EtapaAssinatura;
use App\Enums\FormaPagamento;
use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Parametro;
use App\Models\Requisicao;
use App\Models\RequisicaoEvento;
use App\Models\RequisicaoItem;
use App\Models\User;
use App\Support\DiasUteis;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Http\UploadedFile;
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
                'justificativa' => $validado['justificativa'] ?? '',
                'finalidade' => $validado['finalidade'],
                'data_prevista_devolucao' => $tipo->exigeDevolucao() ? $validado['data_prevista_devolucao'] : null,
                'forma_pagamento' => $tipo === TipoRequisicao::COMPRA_FUNCIONARIO ? $validado['forma_pagamento'] : null,
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
                    'codigo' => $item['codigo'] ?? null,
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

    /**
     * Aprova a requisição. $recusados (id do item => motivo) tira itens do fluxo: os outros seguem.
     * Se todos os itens forem recusados, vira uma reprovação da requisição inteira.
     *
     * @param  array<int|string, mixed>  $recusados
     */
    public function aprovar(Requisicao $requisicao, User $por, ?string $senha, array $recusados = []): Requisicao
    {
        $recusas = $this->normalizarRecusas($requisicao, $recusados);

        if ($this->recusouTudo($requisicao, $recusas)) {
            return $this->reprovar($requisicao, $por, $this->motivoDeTodosRecusados($requisicao, $recusas), $senha);
        }

        return $this->executar(
            $requisicao, $por, StatusRequisicao::APROVADA, 'aprovar', AcaoEvento::APROVACAO,
            EtapaAssinatura::APROVACAO_SETOR, $senha,
            alterar: function (Requisicao $r, array $dados) use ($por) {
                $r->forceFill(['aprovado_por_id' => $por->id, 'aprovado_em' => now()]);
                $this->gravarRecusas($r, $por, $dados['recusas'] ?? []);
            },
            validar: fn () => $recusas === [] ? [] : ['recusas' => $recusas],
        );
    }

    /**
     * Itens que ainda estão no fluxo (não foram recusados).
     *
     * @return Collection<int, RequisicaoItem>
     */
    private function itensAtivos(Requisicao $requisicao): Collection
    {
        return $requisicao->itens->reject(fn (RequisicaoItem $item) => $item->isRecusado())->values();
    }

    /**
     * Só vale recusa de item ativo desta requisição; o motivo é obrigatório.
     *
     * @param  array<int|string, mixed>  $recusados
     * @return array<int, string> motivo por id de item
     */
    private function normalizarRecusas(Requisicao $requisicao, array $recusados): array
    {
        $ativos = $this->itensAtivos($requisicao)->keyBy('id');
        $recusas = [];

        foreach ($recusados as $id => $motivo) {
            $item = $ativos->get((int) $id);

            if ($item === null) {
                continue;
            }

            $recusas[$item->id] = $this->exigirMotivo(
                is_string($motivo) ? $motivo : null,
                "Informe o motivo da recusa de \"{$item->descricao}\"",
                campo: "itens.{$item->id}.motivo",
            );
        }

        return $recusas;
    }

    /**
     * @param  array<int, string>  $recusas
     */
    private function recusouTudo(Requisicao $requisicao, array $recusas): bool
    {
        return $recusas !== [] && count($recusas) === $this->itensAtivos($requisicao)->count();
    }

    /**
     * @param  array<int, string>  $recusas
     */
    private function motivoDeTodosRecusados(Requisicao $requisicao, array $recusas): string
    {
        $texto = $this->itensAtivos($requisicao)
            ->map(fn (RequisicaoItem $item) => "{$item->descricao}: {$recusas[$item->id]}")
            ->implode('; ');

        return mb_substr("Todos os itens foram recusados. {$texto}", 0, 1000);
    }

    /**
     * @param  array<int, string>  $recusas
     */
    private function gravarRecusas(Requisicao $requisicao, User $por, array $recusas): void
    {
        foreach ($requisicao->itens as $item) {
            if (isset($recusas[$item->id])) {
                $item->forceFill([
                    'recusado_em' => now(),
                    'recusado_por_id' => $por->id,
                    'motivo_recusa' => $recusas[$item->id],
                ])->save();
            }
        }
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
    public function separar(Requisicao $requisicao, User $por, array $itens, ?string $senha, ?string $retiradoPorNome = null): Requisicao
    {
        // No Uso e Consumo não há tela de entrega: quem vai retirar é anotado aqui (opcional).
        $retirante = is_string($retiradoPorNome) && trim($retiradoPorNome) !== ''
            ? $this->exigirMotivo($retiradoPorNome, 'Informe quem vai retirar', minimo: 2, campo: 'retirado_por_nome')
            : null;

        return $this->executar(
            $requisicao, $por, StatusRequisicao::EM_SEPARACAO, 'separar', AcaoEvento::SEPARACAO,
            EtapaAssinatura::SEPARACAO, $senha,
            alterar: function (Requisicao $r, array $dados) use ($por, $retirante) {
                $this->gravarSeparacao($r, $por, $dados['itens']);

                if ($retirante !== null) {
                    $r->forceFill(['retirado_por_nome' => $retirante]);
                }
            },
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

        foreach ($this->itensAtivos($requisicao) as $item) {
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
        foreach ($this->itensAtivos($requisicao) as $item) {
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
     * Teste: separa e entrega na mesma tela. É a mesma separação e a mesma entrega de sempre,
     * uma depois da outra, dentro de uma transação só: se algo falhar na entrega,
     * a separação também não vale. As assinaturas ficam encadeadas na ordem: separação, entrega.
     * Uso e Consumo não passa por aqui, porque o líder do estoque libera entre uma etapa e outra.
     *
     * @param  array<int, mixed>  $itens  quantidade separada por id de item
     */
    public function separarEntregar(Requisicao $requisicao, User $por, array $itens, string $retiradoPorNome): Requisicao
    {
        if ($requisicao->tipo !== TipoRequisicao::TESTE) {
            throw RegraDeNegocioException::statusMudou($requisicao);
        }

        // Valida o nome antes de mexer em qualquer coisa.
        $this->exigirMotivo($retiradoPorNome, 'Informe quem está retirando', minimo: 2, campo: 'retirado_por_nome');

        return DB::transaction(function () use ($requisicao, $por, $itens, $retiradoPorNome) {
            $this->separar($requisicao, $por, $itens, null);

            return $this->entregar($requisicao, $por, $retiradoPorNome);
        });
    }

    /**
     * Entrega: o estoquista assina por senha e registra o nome de quem está retirando
     * (pode ser qualquer pessoa buscando o material, não precisa de login).
     */
    public function entregar(Requisicao $requisicao, User $por, string $retiradoPorNome, ?string $senha = null): Requisicao
    {
        $nome = $this->exigirMotivo($retiradoPorNome, 'Informe quem está retirando', minimo: 2, campo: 'retirado_por_nome');

        return $this->executar(
            $requisicao, $por, StatusRequisicao::ENTREGUE, 'entregar', AcaoEvento::ENTREGA,
            EtapaAssinatura::ENTREGA, null,
            alterar: fn (Requisicao $r) => $r->forceFill(['entregue_por_id' => $por->id, 'entregue_em' => now(), 'retirado_por_nome' => $nome]),
        );
    }

    /**
     * Devolução (Teste): numa tela só, confere o que voltou item a item e registra o nome de quem
     * devolveu. Fecha "Devolvida" se tudo voltou bom; qualquer defeito ou falta vira
     * "Devolução com pendência".
     *
     * @param  array<int, array{ok?: mixed, defeito?: mixed, nao_devolvida?: mixed, observacao?: mixed}>  $itens
     */
    public function devolver(Requisicao $requisicao, User $por, array $itens, string $devolvidoPorNome, ?string $senha = null): Requisicao
    {
        $nome = $this->exigirMotivo($devolvidoPorNome, 'Informe quem está devolvendo', minimo: 2, campo: 'devolvido_por_nome');
        $validado = $this->validarDevolucao($requisicao, $itens);
        $comPendencia = collect($validado)->contains(fn (array $linha) => bccomp($linha['defeito'], '0', 3) > 0 || bccomp($linha['nao_devolvida'], '0', 3) > 0);
        $para = $comPendencia ? StatusRequisicao::DEVOLUCAO_COM_PENDENCIA : StatusRequisicao::DEVOLVIDA;

        return $this->executar(
            $requisicao, $por, $para, 'devolver', AcaoEvento::DEVOLUCAO,
            EtapaAssinatura::DEVOLUCAO, null,
            alterar: function (Requisicao $r, array $dados) use ($por, $nome) {
                $this->gravarDevolucao($r, $por, $dados['itens']);
                $r->forceFill(['devolvido_por_nome' => $nome]);
            },
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

        foreach ($this->itensAtivos($requisicao) as $item) {
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

        foreach ($this->itensAtivos($requisicao) as $item) {
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
        foreach ($this->itensAtivos($requisicao) as $item) {
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
     * Baixa (Uso e Consumo): fecha a requisição com o print do documento de baixa do WinThor.
     */
    public function darBaixa(Requisicao $requisicao, User $por, ?UploadedFile $comprovante, ?string $observacao, ?string $senha): Requisicao
    {
        return $this->executar(
            $requisicao, $por, StatusRequisicao::BAIXADA, 'darBaixa', AcaoEvento::BAIXA,
            EtapaAssinatura::BAIXA, $senha,
            alterar: fn (Requisicao $r, array $dados) => $r->forceFill([
                'baixa_por_id' => $por->id,
                'baixa_em' => now(),
                'baixa_comprovante' => $comprovante->store('baixas', 'local'),
                'baixa_observacao' => $dados['observacao'],
            ]),
            validar: function () use ($comprovante, $observacao) {
                Validator::make(['comprovante' => $comprovante], [
                    'comprovante' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
                ], [
                    'comprovante.required' => 'Anexe o print do documento de baixa.',
                    'comprovante.image' => 'O print precisa ser uma imagem (PNG, JPG ou WEBP).',
                    'comprovante.mimes' => 'O print precisa ser uma imagem (PNG, JPG ou WEBP).',
                    'comprovante.max' => 'O print pode ter no máximo 5 MB.',
                    'comprovante.uploaded' => 'Não foi possível enviar o print. Tente de novo.',
                ])->validate();

                return ['observacao' => is_string($observacao) && trim($observacao) !== '' ? trim($observacao) : null];
            },
        );
    }

    /**
     * Pedido de compra: a responsável pela baixa aprova ou reprova depois dos aprovadores.
     * Reprovar exige motivo; aprovar aceita uma observação e grava a forma de pagamento (ela pode
     * trocar a que o solicitante pediu) e, se for parcelado, o número de parcelas.
     */
    public function decidirCompra(
        Requisicao $requisicao,
        User $por,
        bool $aprova,
        ?string $texto,
        ?string $forma = null,
        int|string|null $parcelas = null,
        array $recusados = [],
    ): Requisicao {
        $recusas = $aprova ? $this->normalizarRecusas($requisicao, $recusados) : [];

        // Recusou todos os itens: é o mesmo que reprovar o pedido inteiro.
        if ($this->recusouTudo($requisicao, $recusas)) {
            $aprova = false;
            $texto = $this->motivoDeTodosRecusados($requisicao, $recusas);
            $recusas = [];
        }

        return $this->executar(
            $requisicao, $por,
            $aprova ? StatusRequisicao::COMPRA_APROVADA : StatusRequisicao::COMPRA_REPROVADA,
            'decidirCompra',
            $aprova ? AcaoEvento::COMPRA_APROVADA : AcaoEvento::COMPRA_REPROVADA,
            $aprova ? EtapaAssinatura::COMPRA_APROVADA : EtapaAssinatura::COMPRA_REPROVADA,
            null,
            alterar: function (Requisicao $r, array $dados) use ($por) {
                $r->forceFill(array_merge([
                    'compra_decidido_por_id' => $por->id,
                    'compra_decidido_em' => now(),
                    'compra_observacao' => $dados['observacao'],
                ], array_key_exists('forma_pagamento', $dados) ? [
                    'forma_pagamento' => $dados['forma_pagamento'],
                    'parcelas' => $dados['parcelas'],
                ] : []));
                $this->gravarRecusas($r, $por, $dados['recusas'] ?? []);
            },
            validar: fn () => $aprova
                ? array_merge(
                    ['observacao' => is_string($texto) && trim($texto) !== '' ? trim($texto) : null],
                    $this->validarPagamento($requisicao, $forma, $parcelas),
                    $recusas === [] ? [] : ['recusas' => $recusas],
                )
                : ['observacao' => $this->exigirMotivo($texto, 'Informe o motivo da reprovação da compra', campo: 'observacao')],
        );
    }

    /**
     * @return array{forma_pagamento: string, parcelas: int|null}
     */
    private function validarPagamento(Requisicao $requisicao, ?string $forma, int|string|null $parcelas): array
    {
        $dados = [
            'forma_pagamento' => $forma !== null && $forma !== '' ? $forma : $requisicao->forma_pagamento?->value,
            'parcelas' => $parcelas !== null && $parcelas !== '' ? $parcelas : $requisicao->parcelas,
        ];

        $validado = Validator::make($dados, [
            'forma_pagamento' => ['required', Rule::enum(FormaPagamento::class)],
            'parcelas' => [
                Rule::requiredIf($dados['forma_pagamento'] === FormaPagamento::PARCELADO->value),
                'nullable', 'integer', 'min:2', 'max:24',
            ],
        ], [
            'forma_pagamento.required' => 'Escolha a forma de pagamento: à vista ou parcelado.',
            'parcelas.required' => 'Informe em quantas parcelas.',
            'parcelas.min' => 'Parcelado começa em 2 parcelas.',
            'parcelas.max' => 'O máximo é 24 parcelas.',
            'parcelas.integer' => 'Informe o número de parcelas.',
        ], [
            'forma_pagamento' => 'forma de pagamento',
            'parcelas' => 'parcelas',
        ])->validate();

        return [
            'forma_pagamento' => $validado['forma_pagamento'],
            'parcelas' => $validado['forma_pagamento'] === FormaPagamento::PARCELADO->value ? (int) $validado['parcelas'] : null,
        ];
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
                'codigo' => ($c = $texto($item['codigo'] ?? null)) === '' ? null : $c,
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
            'itens.*.codigo' => ['nullable', 'string', 'max:30'],
            'itens.*.descricao' => ['required', 'string', 'max:255'],
            'itens.*.unidade' => ['required', 'string', 'max:20'],
            'itens.*.quantidade' => ['required', 'numeric', 'gt:0', 'max:999999.999', 'decimal:0,3'],
            'justificativa' => ['nullable', 'string', 'max:2000'],
            'forma_pagamento' => [
                'exclude_unless:tipo,'.TipoRequisicao::COMPRA_FUNCIONARIO->value,
                'required',
                Rule::enum(FormaPagamento::class),
            ],
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
            'forma_pagamento.required' => 'Escolha a forma de pagamento: à vista ou parcelado.',
            'data_prevista_devolucao.required' => 'Informe a data prevista de devolução.',
            'data_prevista_devolucao.after_or_equal' => 'A devolução não pode ser antes de hoje.',
            'data_prevista_devolucao.before_or_equal' => "A devolução pode ser no máximo até {$limite->format('d/m/Y')} ({$prazo} dias úteis a partir da solicitação).",
        ], [
            'tipo' => 'tipo',
            'itens.*.codigo' => 'código do produto',
            'itens.*.descricao' => 'descrição do item',
            'itens.*.unidade' => 'unidade',
            'itens.*.quantidade' => 'quantidade',
            'justificativa' => 'justificativa',
            'finalidade' => 'finalidade',
            'data_prevista_devolucao' => 'data prevista de devolução',
            'forma_pagamento' => 'forma de pagamento',
        ])->validate();
    }
}
