<?php

namespace App\Http\Controllers;

use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
use App\Models\Setor;
use App\Services\RelatorioRequisicoes;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Relatórios de Uso e Consumo, Pedidos de compra e Teste (pedido da Meire), com download para o Excel.
 */
class RelatorioController extends Controller
{
    public function index(Request $request, RelatorioRequisicoes $relatorio): View
    {
        Gate::authorize('acessar-relatorios');
        $filtro = $this->filtro($request);
        $consulta = $relatorio->consulta($filtro);

        $itens = (clone $consulta)->paginate(50)->withQueryString();

        return view('relatorios.index', [
            'filtro' => $filtro,
            'tipos' => RelatorioRequisicoes::TIPOS,
            'colunas' => $relatorio->colunas($filtro['tipo']),
            'linhas' => $itens->getCollection()->map(fn ($item) => $relatorio->linha($item) + [
                'pode_abrir' => $request->user()->can('view', $item->requisicao),
                'status' => $item->requisicao->status,
                'recusado' => $item->isRecusado(),
            ]),
            'itens' => $itens,
            'totalRequisicoes' => (clone $consulta)->reorder()->distinct()->count('requisicoes.id'),
            'setores' => Setor::query()->orderBy('nome')->get(),
            'statusPossiveis' => $relatorio->statusDoTipo($filtro['tipo']),
        ]);
    }

    public function exportar(Request $request, RelatorioRequisicoes $relatorio): StreamedResponse
    {
        Gate::authorize('acessar-relatorios');
        $filtro = $this->filtro($request);
        $colunas = $relatorio->colunas($filtro['tipo']);
        $nome = sprintf('relatorio-%s-%s-a-%s.csv',
            str($filtro['tipo']->rotulo())->slug(), $filtro['de']->format('Y-m-d'), $filtro['ate']->format('Y-m-d'));

        return response()->streamDownload(function () use ($relatorio, $filtro, $colunas) {
            $saida = fopen('php://output', 'w');
            fwrite($saida, "\xEF\xBB\xBF"); // BOM: o Excel abre os acentos certos.
            fputcsv($saida, array_values($colunas), ';', escape: '');
            foreach ($relatorio->consulta($filtro)->lazy(200) as $item) {
                $linha = $relatorio->linha($item);
                fputcsv($saida, array_map(fn ($chave) => $linha[$chave], array_keys($colunas)), ';', escape: '');
            }
            fclose($saida);
        }, $nome, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{tipo: TipoRequisicao, de: Carbon, ate: Carbon, setor_id: ?int, status: ?StatusRequisicao}
     */
    private function filtro(Request $request): array
    {
        $tipo = TipoRequisicao::tryFrom((string) $request->query('tipo'));
        if (! in_array($tipo, RelatorioRequisicoes::TIPOS, true)) {
            $tipo = RelatorioRequisicoes::TIPOS[0];
        }

        $data = function (string $campo, Carbon $padrao): Carbon {
            try {
                return request()->filled($campo) ? Carbon::createFromFormat('Y-m-d', (string) request()->query($campo))->startOfDay() : $padrao;
            } catch (\Throwable) {
                return $padrao;
            }
        };
        $de = $data('de', now()->startOfMonth());
        $ate = $data('ate', now()->startOfDay());
        if ($ate->lt($de)) {
            [$de, $ate] = [$ate, $de];
        }

        $status = StatusRequisicao::tryFrom((string) $request->query('status'));

        return [
            'tipo' => $tipo,
            'de' => $de,
            'ate' => $ate,
            'setor_id' => $request->filled('setor') ? $request->integer('setor') : null,
            'status' => $status,
        ];
    }
}
