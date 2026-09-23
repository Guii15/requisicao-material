<?php

namespace App\Services;

use App\Enums\EtapaAssinatura;
use App\Models\Requisicao;
use App\Models\TentativaAssinatura;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Assinatura por senha: confere a senha de quem está assinando e bloqueia após erros seguidos.
 *
 * O bloqueio é calculado a partir das tentativas gravadas no banco (e não do cache), então
 * sobrevive a um cache:clear e fica auditável. A senha nunca é gravada nem logada.
 */
class ConfirmacaoSenha
{
    public const MAX_ERROS = 5;

    public const JANELA_MINUTOS = 10;

    public const BLOQUEIO_MINUTOS = 15;

    public function confirmar(User $user, ?string $senha, ?Requisicao $requisicao, EtapaAssinatura $etapa): void
    {
        $ultimoBloqueio = $this->ultimoBloqueio($user);

        if ($ultimoBloqueio !== null) {
            $liberaEm = $ultimoBloqueio->addMinutes(self::BLOQUEIO_MINUTOS);

            if (now()->lt($liberaEm)) {
                throw ValidationException::withMessages([
                    'senha' => 'Assinatura bloqueada por excesso de tentativas com senha errada. Tente novamente às '.$liberaEm->format('H:i').'.',
                ]);
            }
        }

        if (is_string($senha) && $senha !== '' && Hash::check($senha, $user->password)) {
            return;
        }

        $errosNaJanela = TentativaAssinatura::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subMinutes(self::JANELA_MINUTOS))
            ->when($ultimoBloqueio, fn ($query) => $query->where('created_at', '>', $ultimoBloqueio))
            ->count();

        $bloqueou = $errosNaJanela + 1 >= self::MAX_ERROS;

        TentativaAssinatura::create([
            'user_id' => $user->id,
            'requisicao_id' => $requisicao?->id,
            'etapa' => $etapa,
            'bloqueou' => $bloqueou,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        throw ValidationException::withMessages([
            'senha' => $bloqueou
                ? 'Senha incorreta. Assinatura bloqueada por '.self::BLOQUEIO_MINUTOS.' minutos por excesso de tentativas.'
                : 'Senha incorreta.',
        ]);
    }

    private function ultimoBloqueio(User $user): ?CarbonImmutable
    {
        $quando = TentativaAssinatura::query()
            ->where('user_id', $user->id)
            ->where('bloqueou', true)
            ->max('created_at');

        return $quando === null ? null : CarbonImmutable::parse($quando);
    }
}
