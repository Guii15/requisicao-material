<?php

use App\Http\Controllers\AprovacaoController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\SenhaController;
use App\Http\Controllers\EstoqueController;
use App\Http\Controllers\PainelController;
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

            Route::get('/painel', [PainelController::class, 'index'])->name('painel.index');

            Route::get('/requisicoes', [RequisicaoController::class, 'index'])->name('requisicoes.index');
            Route::get('/requisicoes/nova', [RequisicaoController::class, 'create'])->name('requisicoes.create');
            Route::post('/requisicoes', [RequisicaoController::class, 'store'])->name('requisicoes.store');
            Route::get('/requisicoes/{requisicao}', [RequisicaoController::class, 'show'])->name('requisicoes.show');
            Route::get('/requisicoes/{requisicao}/pdf', [RequisicaoController::class, 'pdf'])->name('requisicoes.pdf');
            Route::post('/requisicoes/{requisicao}/cancelar', [RequisicaoController::class, 'cancelar'])->name('requisicoes.cancelar');
            Route::post('/requisicoes/{requisicao}/confirmar-recebimento', [RequisicaoController::class, 'confirmarRecebimento'])->name('requisicoes.confirmar-recebimento');

            Route::get('/aprovacoes', [AprovacaoController::class, 'index'])->name('aprovacoes.index');
            Route::post('/requisicoes/{requisicao}/aprovar', [AprovacaoController::class, 'aprovar'])->name('requisicoes.aprovar');
            Route::post('/requisicoes/{requisicao}/reprovar', [AprovacaoController::class, 'reprovar'])->name('requisicoes.reprovar');

            Route::get('/separacao', [EstoqueController::class, 'separacao'])->name('separacao.index');
            Route::post('/requisicoes/{requisicao}/separar', [EstoqueController::class, 'separar'])->name('requisicoes.separar');

            Route::get('/liberacao', [EstoqueController::class, 'liberacao'])->name('liberacao.index');
            Route::post('/requisicoes/{requisicao}/liberar', [EstoqueController::class, 'liberar'])->name('requisicoes.liberar');
            Route::post('/requisicoes/{requisicao}/reprovar-estoque', [EstoqueController::class, 'reprovarEstoque'])->name('requisicoes.reprovar-estoque');

            Route::get('/entrega', [EstoqueController::class, 'entrega'])->name('entrega.index');
            Route::post('/requisicoes/{requisicao}/entregar', [EstoqueController::class, 'entregar'])->name('requisicoes.entregar');

            Route::get('/devolucao', [EstoqueController::class, 'devolucao'])->name('devolucao.index');
            Route::post('/requisicoes/{requisicao}/devolver', [EstoqueController::class, 'devolver'])->name('requisicoes.devolver');

            Route::get('/baixa', [EstoqueController::class, 'baixa'])->name('baixa.index');
            Route::post('/requisicoes/{requisicao}/dar-baixa', [EstoqueController::class, 'darBaixa'])->name('requisicoes.dar-baixa');
        });
    });
});
