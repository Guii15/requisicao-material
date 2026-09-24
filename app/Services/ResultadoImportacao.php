<?php

namespace App\Services;

/**
 * O que a importação de usuários fez (ou faria, na simulação). Vira o relatório do comando.
 */
final class ResultadoImportacao
{
    /** @var list<string> Problemas que impedem a importação: nada é gravado. */
    public array $erros = [];

    /** @var list<string> Linhas deixadas de fora e outros alertas. */
    public array $avisos = [];

    /** @var list<string> */
    public array $setoresCriados = [];

    /** @var array<string, array{nome: string, senha: ?string}> Por login; senha nula quando é a senha de teste. */
    public array $criados = [];

    /** @var array<string, list<string>> Por login, os campos que mudaram. */
    public array $atualizados = [];

    public int $semMudanca = 0;

    /** @var list<string> */
    public array $reativados = [];

    /** @var list<string> */
    public array $desativados = [];

    /** @var list<string> Ativos no sistema que não estão na planilha. */
    public array $foraDaPlanilha = [];

    /** @var array<string, list<string>> Por setor, "Nome (líder)". */
    public array $aprovadores = [];

    public bool $temLiderDoEstoqueAtivo = true;

    public function falhou(): bool
    {
        return $this->erros !== [];
    }
}
