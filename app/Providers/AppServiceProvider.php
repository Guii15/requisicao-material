<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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
    }
}
