<?php

namespace App\Console\Commands;

use App\Services\ImportacaoUsuarios;
use App\Services\ResultadoImportacao;
use App\Support\PlanilhaUsuarios;
use Illuminate\Console\Command;
use RuntimeException;

class ImportarUsuarios extends Command
{
    protected $signature = 'usuarios:importar
        {arquivo : Planilha .xlsx (aba USUARIOS) ou .csv exportado do Excel}
        {--dry-run : Só mostra o que mudaria, sem gravar nada}
        {--desativar-ausentes : Desativa quem está ativo no sistema e não está na planilha}
        {--senha-de-teste= : Fora de produção: todos entram com esta senha, sem troca obrigatória}';

    protected $description = 'Cria e atualiza pessoas, setores e aprovadores a partir da planilha de carga';

    public function handle(ImportacaoUsuarios $importacao): int
    {
        $senhaDeTeste = $this->option('senha-de-teste');

        if ($senhaDeTeste !== null && $this->laravel->isProduction()) {
            $this->error('A senha de teste não pode ser usada em produção: cada pessoa recebe a sua senha provisória.');

            return self::FAILURE;
        }

        try {
            $linhas = PlanilhaUsuarios::ler((string) $this->argument('arquivo'));
        } catch (RuntimeException $erro) {
            $this->error($erro->getMessage());

            return self::FAILURE;
        }

        $simular = (bool) $this->option('dry-run');
        $resultado = $importacao->executar($linhas, $simular, (bool) $this->option('desativar-ausentes'), $senhaDeTeste);

        foreach ($resultado->avisos as $aviso) {
            $this->warn($aviso);
        }

        if ($resultado->falhou()) {
            foreach ($resultado->erros as $erro) {
                $this->error($erro);
            }
            $this->error('Nada foi gravado. Corrija a planilha e rode de novo.');

            return self::FAILURE;
        }

        $this->relatorio($resultado, $simular, $senhaDeTeste !== null);

        return self::SUCCESS;
    }

    private function relatorio(ResultadoImportacao $resultado, bool $simular, bool $comSenhaDeTeste): void
    {
        if ($simular) {
            $this->warn('Simulação: nada foi gravado. Rode sem --dry-run para importar.');
        }

        $this->newLine();
        $this->line('Setores novos: '.($resultado->setoresCriados === [] ? 'nenhum' : implode(', ', $resultado->setoresCriados)));
        $this->line(sprintf(
            'Pessoas: criadas: %d, atualizadas: %d, sem mudança: %d, reativadas: %d, desativadas: %d',
            count($resultado->criados),
            count($resultado->atualizados),
            $resultado->semMudanca,
            count($resultado->reativados),
            count($resultado->desativados),
        ));

        foreach ($resultado->atualizados as $login => $campos) {
            $this->line("  atualizada: {$login} (".implode(', ', $campos).')');
        }
        foreach ($resultado->reativados as $pessoa) {
            $this->line("  reativada: {$pessoa}");
        }
        foreach ($resultado->desativados as $pessoa) {
            $this->line("  desativada: {$pessoa}");
        }

        $this->newLine();
        $this->line('Aprovadores por setor:');
        foreach ($resultado->aprovadores as $setor => $nomes) {
            $this->line("  {$setor}: ".($nomes === [] ? 'nenhum. Os pedidos vão para os líderes do estoque.' : implode(', ', $nomes)));
        }

        if (! $resultado->temLiderDoEstoqueAtivo) {
            $this->warn('Nenhum líder do estoque ativo: os setores sem aprovador ficam sem ninguém para aprovar.');
        }

        if ($resultado->foraDaPlanilha !== []) {
            $this->newLine();
            $this->warn('Fora da planilha (continuam ativos; use --desativar-ausentes para desativar):');
            foreach ($resultado->foraDaPlanilha as $pessoa) {
                $this->line("  {$pessoa}");
            }
        }

        if ($resultado->criados === []) {
            return;
        }

        $this->newLine();

        if ($simular) {
            $this->line('Na importação de verdade, cada pessoa nova recebe uma senha provisória.');
        } elseif ($comSenhaDeTeste) {
            $this->line('As pessoas entram com a senha de teste informada, sem troca obrigatória.');
        } else {
            $this->line('Senhas provisórias. Entregue a cada pessoa; o sistema pede a troca no primeiro acesso:');
            $this->table(
                ['Login', 'Senha provisória', 'Nome'],
                collect($resultado->criados)->map(fn (array $criado, string $login) => [$login, $criado['senha'], $criado['nome']])->values()->all(),
            );
        }

        $this->warn('Apague a planilha do servidor depois de importar: ela tem dados pessoais.');
    }
}
