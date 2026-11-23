<?php

namespace App\Providers;

use App\Enums\TipoRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use App\Services\FilaDeAprovacao;
use App\Services\FilaDeEnvio;
use App\Services\FilaDeLiberacao;
use App\Services\QuemAtende;
use App\View\Composers\MenuComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fora de produção: erro na hora para N+1, atributo descartado em silêncio e campo inexistente.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Em produção, bloqueia migrate:fresh, db:wipe e afins.
        DB::prohibitDestructiveCommands($this->app->isProduction());

        // 5 tentativas de login por minuto para cada combinação usuário + IP.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower(trim((string) $request->input('login'))).'|'.$request->ip()));

        // Aprovações: só quem aprova algo pelas regras atuais (líder do estoque, quem aprova compras e
        // quem aprova transferência). Líder de setor sozinho não aprova mais nada, então não vê o menu (23/11/2026).
        Gate::define('acessar-aprovacoes', fn (User $user) => $user->is_lider_estoque || $user->aprova_compras
            || app(FilaDeAprovacao::class)->aprovaTransferencia($user));

        // Fila de separação: qualquer um do setor Estoque, de qualquer setor solicitante.
        Gate::define('acessar-separacao', fn (User $user) => $user->is_estoque || app(QuemAtende::class)->setorRecebePedidos($user));

        // Liberação é só de Uso e Consumo, só líder do estoque.
        // O menu só aparece quando há algo para liberar: hoje quase nunca acontece (23/11/2026).
        Gate::define('acessar-liberacao', fn (User $user) => $user->is_lider_estoque
            && app(FilaDeLiberacao::class)->para($user)->exists());

        // Devolução é só de Teste.
        Gate::define('acessar-devolucao', fn (User $user) => $user->is_estoque || app(QuemAtende::class)->setorRecebePedidos($user));

        // Baixa é só de Uso e Consumo: a responsável, ou o Admin (só vê o caso "própria responsável abriu o pedido").
        Gate::define('acessar-baixa', fn (User $user) => $user->is_responsavel_baixa || $user->is_admin);

        // Pedidos de compra: a responsável pela baixa decide.
        Gate::define('acessar-compras', fn (User $user) => $user->is_responsavel_baixa);

        // Transferência de filial: quem faz a transferência no WinThor e os líderes do estoque, que recebem.
        Gate::define('acessar-transferencias', fn (User $user) => $user->faz_transferencia);
        // Envio: qualquer pessoa pode enviar uma transferência (menos quem pediu); o menu aparece
        // para o estoque sempre e para os outros quando há transferência esperando envio (18/11/2026).
        Gate::define('acessar-envio', fn (User $user) => $user->is_estoque
            || app(FilaDeEnvio::class)->para($user)->exists());
        // Recebimento da transferência: quem pediu confirma que chegou (decisão de 16/11/2026).
        Gate::define('acessar-recebimento', fn (User $user) => Requisicao::query()
            ->where('solicitante_id', $user->id)
            ->where('tipo', TipoRequisicao::TRANSFERENCIA_FILIAL->value)
            ->exists());

        // Tela Usuários: criar login e dar permissões. Só administrador ativo.
        // Relatórios de Uso e Consumo, Pedidos de compra e Teste: a responsável pela baixa e o Admin.
        Gate::define('acessar-relatorios', fn (User $user) => $user->is_responsavel_baixa || $user->is_admin);

        Gate::define('gerenciar-usuarios', fn (User $user) => $user->ativo && $user->is_admin);

        View::composer('components.layouts.app', MenuComposer::class);
    }
}
