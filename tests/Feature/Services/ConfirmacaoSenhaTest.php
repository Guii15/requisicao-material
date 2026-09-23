<?php

namespace Tests\Feature\Services;

use App\Enums\EtapaAssinatura;
use App\Models\TentativaAssinatura;
use App\Models\User;
use App\Services\ConfirmacaoSenha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ConfirmacaoSenhaTest extends TestCase
{
    use RefreshDatabase;

    private ConfirmacaoSenha $senhas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-24 10:00:00');
        $this->senhas = app(ConfirmacaoSenha::class);
    }

    private function tentar(User $user, ?string $senha): ?string
    {
        try {
            $this->senhas->confirmar($user, $senha, null, EtapaAssinatura::APROVACAO_SETOR);

            return null;
        } catch (ValidationException $e) {
            return $e->errors()['senha'][0];
        }
    }

    public function test_senha_correta_passa_sem_registrar_tentativa(): void
    {
        $user = User::factory()->create();

        $this->assertNull($this->tentar($user, 'password'));
        $this->assertSame(0, TentativaAssinatura::count());
    }

    public function test_senha_errada_ou_vazia_e_recusada_e_registrada(): void
    {
        $user = User::factory()->create();

        $this->assertSame('Senha incorreta.', $this->tentar($user, 'errada'));
        $this->assertNotNull($this->tentar($user, null));
        $this->assertSame(2, TentativaAssinatura::where('user_id', $user->id)->where('bloqueou', false)->count());
    }

    public function test_cinco_senhas_erradas_em_dez_minutos_bloqueiam_por_quinze_minutos(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 4) as $minuto) {
            $this->travelTo("2026-09-24 10:0{$minuto}:00");
            $this->tentar($user, 'errada');
        }
        $this->travelTo('2026-09-24 10:05:00');
        $mensagem = $this->tentar($user, 'errada');

        $this->assertStringContainsString('bloqueada', $mensagem);
        $this->assertSame(1, TentativaAssinatura::where('bloqueou', true)->count());

        // Bloqueada até 10:20, mesmo com a senha certa.
        $this->travelTo('2026-09-24 10:19:00');
        $this->assertStringContainsString('10:20', $this->tentar($user, 'password'));

        $this->travelTo('2026-09-24 10:20:01');
        $this->assertNull($this->tentar($user, 'password'));
    }

    public function test_erros_com_mais_de_dez_minutos_nao_contam(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 4) as $minuto) {
            $this->travelTo("2026-09-24 10:0{$minuto}:00");
            $this->tentar($user, 'errada');
        }
        $this->travelTo('2026-09-24 10:12:00');

        $this->assertSame('Senha incorreta.', $this->tentar($user, 'errada'));
        $this->assertSame(0, TentativaAssinatura::where('bloqueou', true)->count());
    }

    public function test_bloqueio_e_por_usuario(): void
    {
        $bloqueado = User::factory()->create();
        $outro = User::factory()->create();

        foreach (range(1, 5) as $i) {
            $this->tentar($bloqueado, 'errada');
        }

        $this->assertNull($this->tentar($outro, 'password'));
    }
}
