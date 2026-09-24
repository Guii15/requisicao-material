<?php

use App\Http\Controllers\AprovacaoController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\SenhaController;
use App\Http\Controllers\RequisicaoController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/entrar', [LoginController::class, 'create'])->name('login');
    Route::post('/entrar', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/sair', [LoginController::class, 'destroy'])->name('logout');

    Route::middleware('ativo')->group(function () {
        Route::get('/senha', [SenhaController::class, 'edit'])->name('senha.edit');
        Route::put('/senha', [SenhaController::class, 'update'])->name('senha.update');

        Route::middleware('senha.trocada')->group(function () {
            Route::redirect('/', '/requisicoes')->name('inicio');

            Route::get('/requisicoes', [RequisicaoController::class, 'index'])->name('requisicoes.index');
            Route::get('/requisicoes/nova', [RequisicaoController::class, 'create'])->name('requisicoes.create');
            Route::post('/requisicoes', [RequisicaoController::class, 'store'])->name('requisicoes.store');
            Route::get('/requisicoes/{requisicao}', [RequisicaoController::class, 'show'])->name('requisicoes.show');
            Route::get('/requisicoes/{requisicao}/pdf', [RequisicaoController::class, 'pdf'])->name('requisicoes.pdf');
            Route::post('/requisicoes/{requisicao}/cancelar', [RequisicaoController::class, 'cancelar'])->name('requisicoes.cancelar');

            Route::get('/aprovacoes', [AprovacaoController::class, 'index'])->name('aprovacoes.index');
            Route::post('/requisicoes/{requisicao}/aprovar', [AprovacaoController::class, 'aprovar'])->name('requisicoes.aprovar');
            Route::post('/requisicoes/{requisicao}/reprovar', [AprovacaoController::class, 'reprovar'])->name('requisicoes.reprovar');
        });
    });
});
