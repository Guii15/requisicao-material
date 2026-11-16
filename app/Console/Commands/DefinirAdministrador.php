<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Marca (ou desmarca) quem é administrador. Serve para o primeiro acesso no servidor:
 * depois disso, os administradores dão as permissões pela tela Usuários.
 */
class DefinirAdministrador extends Command
{
    protected $signature = 'usuarios:admin
        {login* : Um ou mais logins, separados por espaço}
        {--remover : Tira o acesso de administrador em vez de dar}';

    protected $description = 'Dá (ou tira, com --remover) o acesso de administrador, que cria logins e dá permissões';

    public function handle(): int
    {
        $remover = (bool) $this->option('remover');
        $logins = array_map(fn (string $l) => mb_strtolower(trim($l)), (array) $this->argument('login'));
        $usuarios = User::query()->whereIn('login', $logins)->get()->keyBy('login');

        $faltando = array_diff($logins, $usuarios->keys()->all());
        if ($faltando !== []) {
            $this->error('Login não encontrado: '.implode(', ', $faltando).'. Nada foi alterado.');

            return self::FAILURE;
        }

        if ($remover) {
            $restantes = User::query()->where('is_admin', true)->where('ativo', true)->whereNotIn('login', $logins)->count();
            if ($restantes === 0) {
                $this->error('O sistema precisa ter pelo menos um administrador ativo. Nada foi alterado.');

                return self::FAILURE;
            }
        }

        foreach ($usuarios as $usuario) {
            $usuario->forceFill(['is_admin' => ! $remover])->save();
            $this->line(($remover ? 'Não é mais administrador: ' : 'Administrador: ')."{$usuario->nome} ({$usuario->login})"
                .(! $remover && ! $usuario->ativo ? ' (atenção: está desativado)' : ''));
        }

        return self::SUCCESS;
    }
}
