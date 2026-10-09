<?php

namespace Tests\Unit\Enums;

use App\Enums\StatusRequisicao as S;
use App\Enums\TipoRequisicao as T;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StatusRequisicaoTest extends TestCase
{
    /**
     * Tabela completa de transições permitidas (fonte da verdade da spec + decisões de 23/09/2026).
     *
     * @return array<string, array{S, S, T}>
     */
    public static function transicoesPermitidas(): array
    {
        $casos = [];
        $add = function (S $de, S $para, T ...$tipos) use (&$casos) {
            foreach ($tipos as $tipo) {
                $casos["{$de->value} -> {$para->value} ({$tipo->value})"] = [$de, $para, $tipo];
            }
        };
        $ambos = [T::TESTE, T::USO_CONSUMO];

        $add(S::AGUARDANDO_APROVACAO, S::APROVADA, ...$ambos);
        $add(S::AGUARDANDO_APROVACAO, S::REPROVADA, ...$ambos);
        $add(S::AGUARDANDO_APROVACAO, S::CANCELADA, ...$ambos);
        $add(S::APROVADA, S::EM_SEPARACAO, ...$ambos);
        $add(S::APROVADA, S::CANCELADA, ...$ambos);
        $add(S::EM_SEPARACAO, S::PRONTA_PARA_RETIRADA, T::TESTE);
        $add(S::EM_SEPARACAO, S::AGUARDANDO_LIBERACAO_ESTOQUE, T::USO_CONSUMO);
        $add(S::EM_SEPARACAO, S::CANCELADA, ...$ambos);
        $add(S::AGUARDANDO_LIBERACAO_ESTOQUE, S::LIBERADA, T::USO_CONSUMO);
        $add(S::AGUARDANDO_LIBERACAO_ESTOQUE, S::REPROVADA_ESTOQUE, T::USO_CONSUMO);
        $add(S::AGUARDANDO_LIBERACAO_ESTOQUE, S::CANCELADA, T::USO_CONSUMO);
        $add(S::LIBERADA, S::PRONTA_PARA_RETIRADA, T::USO_CONSUMO);
        $add(S::PRONTA_PARA_RETIRADA, S::ENTREGUE, ...$ambos);
        $add(S::PRONTA_PARA_RETIRADA, S::CANCELADA, ...$ambos);
        $add(S::ENTREGUE, S::EM_POSSE, T::TESTE);
        $add(S::ENTREGUE, S::AGUARDANDO_BAIXA, T::USO_CONSUMO);
        $add(S::EM_POSSE, S::DEVOLVIDA, T::TESTE);
        $add(S::EM_POSSE, S::DEVOLUCAO_COM_PENDENCIA, T::TESTE);
        $add(S::AGUARDANDO_BAIXA, S::BAIXADA, T::USO_CONSUMO);
        // Compra de funcionário não passa pelo estoque: vai do aprovador direto para a compra.
        $add(S::AGUARDANDO_APROVACAO, S::APROVADA, T::COMPRA_FUNCIONARIO);
        $add(S::AGUARDANDO_APROVACAO, S::REPROVADA, T::COMPRA_FUNCIONARIO);
        $add(S::AGUARDANDO_APROVACAO, S::CANCELADA, T::COMPRA_FUNCIONARIO);
        $add(S::APROVADA, S::AGUARDANDO_COMPRA, T::COMPRA_FUNCIONARIO);
        $add(S::APROVADA, S::CANCELADA, T::COMPRA_FUNCIONARIO);
        $add(S::AGUARDANDO_COMPRA, S::COMPRA_APROVADA, T::COMPRA_FUNCIONARIO);
        $add(S::AGUARDANDO_COMPRA, S::COMPRA_REPROVADA, T::COMPRA_FUNCIONARIO);
        $add(S::AGUARDANDO_COMPRA, S::CANCELADA, T::COMPRA_FUNCIONARIO);

        return $casos;
    }

    #[DataProvider('transicoesPermitidas')]
    public function test_transicao_da_tabela_e_permitida(S $de, S $para, T $tipo): void
    {
        $this->assertTrue($de->podeIrPara($para, $tipo));
    }

    public function test_nenhuma_transicao_fora_da_tabela_e_permitida(): void
    {
        $permitidas = array_keys(self::transicoesPermitidas());

        foreach (S::cases() as $de) {
            foreach (S::cases() as $para) {
                foreach (T::cases() as $tipo) {
                    // Compra de funcionário nunca chega nos status do fluxo de estoque.
                    if ($tipo === T::COMPRA_FUNCIONARIO && ! in_array($de, [S::AGUARDANDO_APROVACAO, S::APROVADA, S::AGUARDANDO_COMPRA], true)) {
                        continue;
                    }
                    // Aguardando compra só existe para compra de funcionário.
                    if ($de === S::AGUARDANDO_COMPRA && $tipo !== T::COMPRA_FUNCIONARIO) {
                        continue;
                    }
                    $chave = "{$de->value} -> {$para->value} ({$tipo->value})";
                    if (in_array($chave, $permitidas, true)) {
                        continue;
                    }
                    $this->assertFalse($de->podeIrPara($para, $tipo), "Não deveria permitir: {$chave}");
                }
            }
        }
    }

    public function test_teste_nao_passa_pela_liberacao_do_estoque(): void
    {
        $this->assertFalse(S::EM_SEPARACAO->podeIrPara(S::AGUARDANDO_LIBERACAO_ESTOQUE, T::TESTE));
    }

    public function test_uso_e_consumo_nao_pula_a_liberacao_do_estoque(): void
    {
        $this->assertFalse(S::EM_SEPARACAO->podeIrPara(S::PRONTA_PARA_RETIRADA, T::USO_CONSUMO));
    }

    public function test_aguardando_aprovacao_nao_vai_direto_para_entregue(): void
    {
        $this->assertFalse(S::AGUARDANDO_APROVACAO->podeIrPara(S::ENTREGUE, T::TESTE));
        $this->assertFalse(S::AGUARDANDO_APROVACAO->podeIrPara(S::ENTREGUE, T::USO_CONSUMO));
    }

    public function test_status_de_passagem_seguem_automaticamente_conforme_o_tipo(): void
    {
        $this->assertSame(S::PRONTA_PARA_RETIRADA, S::LIBERADA->proximoAutomatico(T::USO_CONSUMO));
        $this->assertSame(S::EM_POSSE, S::ENTREGUE->proximoAutomatico(T::TESTE));
        $this->assertSame(S::AGUARDANDO_BAIXA, S::ENTREGUE->proximoAutomatico(T::USO_CONSUMO));
        $this->assertNull(S::APROVADA->proximoAutomatico(T::TESTE));
        $this->assertSame(S::AGUARDANDO_COMPRA, S::APROVADA->proximoAutomatico(T::COMPRA_FUNCIONARIO));
        $this->assertNull(S::EM_POSSE->proximoAutomatico(T::TESTE));
    }

    public function test_status_finais_nao_tem_saida(): void
    {
        $finais = [S::REPROVADA, S::CANCELADA, S::REPROVADA_ESTOQUE, S::DEVOLVIDA, S::DEVOLUCAO_COM_PENDENCIA, S::BAIXADA, S::COMPRA_APROVADA, S::COMPRA_REPROVADA];

        foreach (S::cases() as $status) {
            $this->assertSame(in_array($status, $finais, true), $status->isFinal(), $status->value);
        }
    }

    public function test_estoque_nunca_ve_requisicao_nao_aprovada(): void
    {
        $invisiveis = [S::AGUARDANDO_APROVACAO, S::REPROVADA, S::CANCELADA, S::AGUARDANDO_COMPRA, S::COMPRA_APROVADA, S::COMPRA_REPROVADA];

        foreach (S::cases() as $status) {
            $this->assertSame(! in_array($status, $invisiveis, true), $status->visivelParaEstoque(), $status->value);
        }
    }

    public function test_todo_status_tem_rotulo_em_portugues(): void
    {
        foreach (S::cases() as $status) {
            $this->assertNotSame('', $status->rotulo());
        }
        $this->assertSame('Aguardando liberação do estoque', S::AGUARDANDO_LIBERACAO_ESTOQUE->rotulo());
    }

    public function test_grupo_visual_de_cada_status(): void
    {
        $this->assertSame('aguardando', S::AGUARDANDO_APROVACAO->grupo());
        $this->assertSame('aguardando', S::AGUARDANDO_BAIXA->grupo());
        $this->assertSame('andamento', S::EM_SEPARACAO->grupo());
        $this->assertSame('concluida', S::DEVOLVIDA->grupo());
        $this->assertSame('concluida', S::BAIXADA->grupo());
        $this->assertSame('encerrada', S::REPROVADA->grupo());
        $this->assertSame('encerrada', S::CANCELADA->grupo());
        $this->assertSame('alerta', S::DEVOLUCAO_COM_PENDENCIA->grupo());

        foreach (S::cases() as $status) {
            $this->assertContains($status->grupo(), ['aguardando', 'andamento', 'concluida', 'encerrada', 'alerta']);
        }
    }
}
