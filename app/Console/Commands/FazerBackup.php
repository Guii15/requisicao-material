<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Finder\SplFileInfo;
use ZipArchive;

class FazerBackup extends Command
{
    protected $signature = 'backup:fazer
        {--pasta= : Onde guardar os backups (padrão: storage/app/backups)}
        {--manter=30 : Quantos backups antigos ficam na pasta}';

    protected $description = 'Gera um .zip com o banco e os arquivos privados (assinaturas e prints)';

    public function handle(): int
    {
        $pasta = rtrim((string) ($this->option('pasta') ?: storage_path('app/backups')), '/');
        $manter = max(1, (int) $this->option('manter'));

        File::ensureDirectoryExists($pasta);

        $destino = $pasta.'/backup-'.now()->format('Y-m-d_His').'.zip';
        $copiaDoBanco = $pasta.'/.banco-'.uniqid().'.sqlite';

        try {
            $this->copiarBanco($copiaDoBanco);
            $this->compactar($destino, $copiaDoBanco);
        } catch (RuntimeException $erro) {
            File::delete($destino);
            $this->error($erro->getMessage());

            return self::FAILURE;
        } finally {
            File::delete($copiaDoBanco);
        }

        $this->apagarAntigos($pasta, $manter);
        $this->info('Backup gravado em '.$destino);

        return self::SUCCESS;
    }

    /**
     * VACUUM INTO tira uma cópia consistente mesmo com gente usando o sistema,
     * ao contrário de copiar o arquivo .sqlite no meio de uma gravação.
     */
    private function copiarBanco(string $destino): void
    {
        $conexao = DB::connection();

        if ($conexao->getDriverName() !== 'sqlite') {
            throw new RuntimeException('O backup:fazer só sabe copiar banco SQLite. Com MySQL, use o mysqldump.');
        }

        $conexao->statement('VACUUM INTO ?', [$destino]);
    }

    private function compactar(string $destino, string $copiaDoBanco): void
    {
        $zip = new ZipArchive;

        if ($zip->open($destino, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException('Não foi possível criar '.$destino);
        }

        $zip->addFile($copiaDoBanco, 'database.sqlite');

        $privados = storage_path('app/private');

        foreach (File::allFiles($privados) as $arquivo) {
            /** @var SplFileInfo $arquivo */
            $zip->addFile($arquivo->getPathname(), 'private/'.$arquivo->getRelativePathname());
        }

        if (! $zip->close()) {
            throw new RuntimeException('Não foi possível gravar '.$destino);
        }
    }

    private function apagarAntigos(string $pasta, int $manter): void
    {
        $backups = collect(File::glob($pasta.'/backup-*.zip'))->sort()->values();

        $backups->slice(0, max(0, $backups->count() - $manter))->each(fn (string $antigo) => File::delete($antigo));
    }
}
