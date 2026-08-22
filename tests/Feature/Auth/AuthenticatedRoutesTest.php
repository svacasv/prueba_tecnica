<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\ApiTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthenticatedRoutesTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return app(ApiTokenService::class)->issue($user)->plainText;
    }

    public function test_devuelve_el_usuario_autenticado(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_rechaza_las_peticiones_sin_token(): void
    {
        $this->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_rechaza_un_token_que_no_existe(): void
    {
        $this->withToken('un-token-inventado')
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_rechaza_un_token_caducado(): void
    {
        config()->set('auth.api_token.lifetime_days', 1);
        $token = $this->tokenFor(User::factory()->create());

        $this->travel(2)->days();

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_cerrar_sesion_invalida_el_token_usado(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $otherToken = $this->tokenFor($user);

        $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();

        // Dentro de un mismo test Laravel reutiliza el guard con el usuario ya
        // resuelto. En HTTP real cada petición es un proceso nuevo; aquí se simula.
        Auth::forgetGuards();

        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();

        // Los demás tokens del usuario (otros dispositivos) siguen funcionando.
        Auth::forgetGuards();
        $this->withToken($otherToken)->getJson('/api/auth/me')->assertOk();
    }

    public function test_cerrar_sesion_requiere_estar_autenticado(): void
    {
        $this->postJson('/api/auth/logout')->assertUnauthorized();
    }
}
