<?php

namespace App\Providers;

use App\Models\User;
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

        Gate::define('acessar-aprovacoes', fn (User $user) => $user->is_admin || $user->setoresAprovados()->exists());

        View::composer('components.layouts.app', MenuComposer::class);
    }
}
