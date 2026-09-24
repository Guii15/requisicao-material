<?php

namespace App\Support;

use DateTimeInterface;
use OpenSpout\Reader\CSV\Options as OpcoesCsv;
use OpenSpout\Reader\CSV\Reader as LeitorCsv;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\SheetInterface;
use OpenSpout\Reader\XLSX\Options as OpcoesXlsx;
use OpenSpout\Reader\XLSX\Reader as LeitorXlsx;
use RuntimeException;

/**
 * Lê a planilha de carga de usuários: .xlsx (aba USUARIOS) ou .csv exportado do Excel.
 * Cada linha vem com o número dela na planilha e as colunas pelo nome do cabeçalho
 * (maiúsculas, sem acento), para as mensagens de erro apontarem a linha certa.
 */
final class PlanilhaUsuarios
{
    public const ABA = 'USUARIOS';

    /**
     * @return list<array{linha: int, colunas: array<string, string>}>
     *
     * @throws RuntimeException arquivo inexistente, formato errado ou sem a aba USUARIOS
     */
    public static function ler(string $caminho): array
    {
        if (! is_file($caminho)) {
            throw new RuntimeException("Arquivo não encontrado: {$caminho}");
        }

        $leitor = match (strtolower(pathinfo($caminho, PATHINFO_EXTENSION))) {
            'xlsx' => new LeitorXlsx(new OpcoesXlsx(SHOULD_PRESERVE_EMPTY_ROWS: true)),
            'csv' => new LeitorCsv(self::opcoesCsv($caminho)),
            default => throw new RuntimeException('Use a planilha .xlsx ou um arquivo .csv.'),
        };

        $leitor->open($caminho);

        try {
            return self::linhas(self::aba($leitor));
        } finally {
            $leitor->close();
        }
    }

    /**
     * Texto do cabeçalho como a importação espera: "Líder Estoque" vira "LIDER_ESTOQUE".
     */
    public static function normalizar(string $texto): string
    {
        $semAcento = strtr(mb_strtoupper(trim($texto)), ['Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'É' => 'E', 'Ê' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ú' => 'U', 'Ç' => 'C']);

        return (string) preg_replace('/\s+/', '_', $semAcento);
    }

    private static function aba(ReaderInterface $leitor): SheetInterface
    {
        $abas = iterator_to_array($leitor->getSheetIterator(), false);

        foreach ($abas as $aba) {
            if (self::normalizar($aba->getName()) === self::ABA) {
                return $aba;
            }
        }

        // CSV (ou planilha de uma aba só): vale a única aba que existe.
        if (count($abas) === 1) {
            return $abas[0];
        }

        throw new RuntimeException('A planilha não tem a aba '.self::ABA.'.');
    }

    /**
     * @return list<array{linha: int, colunas: array<string, string>}>
     */
    private static function linhas(SheetInterface $aba): array
    {
        $cabecalho = null;
        $linhas = [];
        $numero = 0;

        foreach ($aba->getRowIterator() as $row) {
            $numero++;
            $valores = array_map(self::texto(...), $row->toArray());

            if (array_filter($valores, fn (string $valor) => $valor !== '') === []) {
                continue;
            }

            if ($cabecalho === null) {
                $cabecalho = array_map(self::normalizar(...), $valores);

                continue;
            }

            $colunas = [];
            foreach ($cabecalho as $indice => $nome) {
                if ($nome !== '') {
                    $colunas[$nome] = $valores[$indice] ?? '';
                }
            }

            $linhas[] = ['linha' => $numero, 'colunas' => $colunas];
        }

        return $linhas;
    }

    private static function texto(mixed $valor): string
    {
        $texto = match (true) {
            $valor === null => '',
            is_float($valor) && floor($valor) === $valor => (string) (int) $valor,
            $valor instanceof DateTimeInterface => $valor->format('Y-m-d'),
            is_bool($valor) => $valor ? 'SIM' : 'NÃO',
            default => (string) $valor,
        };

        // Espaço "duro" (colado de outros sistemas) conta como espaço comum.
        return trim(str_replace("\u{00A0}", ' ', $texto));
    }

    /**
     * O Excel em português salva CSV com ";" e, às vezes, em ANSI (Windows-1252).
     */
    private static function opcoesCsv(string $caminho): OpcoesCsv
    {
        $conteudo = (string) file_get_contents($caminho);
        $primeiraLinha = (string) strtok($conteudo, "\r\n");

        return new OpcoesCsv(
            FIELD_DELIMITER: substr_count($primeiraLinha, ';') > substr_count($primeiraLinha, ',') ? ';' : ',',
            ENCODING: mb_check_encoding($conteudo, 'UTF-8') ? 'UTF-8' : 'Windows-1252',
        );
    }
}
