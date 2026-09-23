<?php

use App\Http\Middleware\ExigirTrocaDeSenha;
use App\Http\Middleware\GarantirUsuarioAtivo;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'ativo' => GarantirUsuarioAtivo::class,
            'senha.trocada' => ExigirTrocaDeSenha::class,
        ]);
        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Senhas nunca voltam para a sessão como "old input" depois de um erro de validação.
        $exceptions->dontFlash(['senha', 'senha_atual', 'nova_senha', 'nova_senha_confirmation']);
    })->create();
