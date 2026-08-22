<?php

namespace Tests\Unit\Auth;

use App\Models\ApiToken;
use App\Models\User;
use App\Services\Auth\ApiTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_emite_un_token_aleatorio_y_guarda_solo_su_hash(): void
    {
        $user = User::factory()->create();

        $issued = (new ApiTokenService(lifetimeDays: 30))->issue($user);

        $this->assertSame(64, strlen($issued->plainText));
        $this->assertDatabaseCount('api_tokens', 1);
        $this->assertDatabaseMissing('api_tokens', ['token_hash' => $issued->plainText]);
        $this->assertDatabaseHas('api_tokens', [
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $issued->plainText),
        ]);
    }

    public function test_el_token_caduca_segun_la_configuracion(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();

        $withExpiry = (new ApiTokenService(lifetimeDays: 7))->issue($user);
        $withoutExpiry = (new ApiTokenService(lifetimeDays: 0))->issue($user);

        $this->assertTrue($withExpiry->expiresAt->equalTo(now()->addDays(7)));
        $this->assertNull($withoutExpiry->expiresAt);
    }

    public function test_resuelve_el_usuario_a_partir_del_token_y_anota_el_ultimo_uso(): void
    {
        // freezeSecond y no freezeTime: la columna timestamp de MySQL no guarda microsegundos.
        $this->freezeSecond();
        $user = User::factory()->create();
        $service = new ApiTokenService(lifetimeDays: 30);
        $issued = $service->issue($user);

        $resolved = $service->userFromToken($issued->plainText);

        $this->assertTrue($resolved->is($user));
        $this->assertTrue(ApiToken::query()->sole()->last_used_at->equalTo(now()));
    }

    public function test_no_resuelve_tokens_inexistentes_vacios_o_caducados(): void
    {
        $user = User::factory()->create();
        $service = new ApiTokenService(lifetimeDays: 1);
        $issued = $service->issue($user);

        $this->assertNull($service->userFromToken(null));
        $this->assertNull($service->userFromToken(''));
        $this->assertNull($service->userFromToken('un-token-que-no-existe'));

        $this->travel(2)->days();

        $this->assertNull($service->userFromToken($issued->plainText), 'El token ha caducado');
    }

    public function test_revocar_un_token_lo_elimina(): void
    {
        $user = User::factory()->create();
        $service = new ApiTokenService(lifetimeDays: 30);
        $issued = $service->issue($user);

        $service->revoke($issued->plainText);

        $this->assertDatabaseCount('api_tokens', 0);
        $this->assertNull($service->userFromToken($issued->plainText));
    }
}
