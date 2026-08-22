<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_devuelve_un_token_con_credenciales_correctas(): void
    {
        $user = User::factory()->create(['email' => 'morty@example.com', 'password' => 'aw-geez-rick']);

        $response = $this->postJson('/api/auth/login', ['email' => 'morty@example.com', 'password' => 'aw-geez-rick']);

        $response
            ->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'expires_at', 'user'])
            ->assertJsonPath('user.id', $user->id);

        $this->assertDatabaseHas('api_tokens', ['user_id' => $user->id]);
    }

    public function test_cada_inicio_de_sesion_emite_un_token_distinto(): void
    {
        User::factory()->create(['email' => 'morty@example.com', 'password' => 'aw-geez-rick']);
        $credentials = ['email' => 'morty@example.com', 'password' => 'aw-geez-rick'];

        $first = $this->postJson('/api/auth/login', $credentials)->json('token');
        $second = $this->postJson('/api/auth/login', $credentials)->json('token');

        $this->assertNotSame($first, $second);
        $this->assertDatabaseCount('api_tokens', 2);
    }

    public function test_rechaza_una_contrasena_incorrecta(): void
    {
        User::factory()->create(['email' => 'morty@example.com', 'password' => 'aw-geez-rick']);

        $this->postJson('/api/auth/login', ['email' => 'morty@example.com', 'password' => 'incorrecta'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid credentials.');

        $this->assertDatabaseCount('api_tokens', 0);
    }

    public function test_responde_igual_si_el_email_no_existe(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'nadie@example.com', 'password' => 'lo-que-sea'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_valida_los_datos_de_entrada(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'no-es-un-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_limita_los_intentos_de_inicio_de_sesion(): void
    {
        $credentials = ['email' => 'nadie@example.com', 'password' => 'lo-que-sea'];

        foreach (range(1, 10) as $attempt) {
            $this->postJson('/api/auth/login', $credentials)->assertUnauthorized();
        }

        $this->postJson('/api/auth/login', $credentials)->assertTooManyRequests();
    }
}
