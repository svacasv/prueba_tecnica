<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Rick Sanchez',
            'email' => 'rick@example.com',
            'password' => 'wubba-lubba-dub-dub',
        ], $overrides);
    }

    public function test_registra_un_usuario_y_devuelve_un_token(): void
    {
        $response = $this->postJson('/api/auth/register', $this->payload());

        $response
            ->assertCreated()
            ->assertJsonStructure(['token', 'token_type', 'expires_at', 'user' => ['id', 'name', 'email', 'created_at']])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'rick@example.com')
            ->assertJsonMissingPath('user.password');

        $this->assertDatabaseHas('users', ['email' => 'rick@example.com']);
        $this->assertDatabaseCount('api_tokens', 1);
    }

    public function test_la_contrasena_se_guarda_cifrada(): void
    {
        $this->postJson('/api/auth/register', $this->payload())->assertCreated();

        $user = User::query()->sole();

        $this->assertNotSame('wubba-lubba-dub-dub', $user->password);
        $this->assertTrue(Hash::check('wubba-lubba-dub-dub', $user->password));
    }

    public function test_no_permite_registrar_dos_veces_el_mismo_email(): void
    {
        User::factory()->create(['email' => 'rick@example.com']);

        $this->postJson('/api/auth/register', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_valida_los_datos_de_entrada(): void
    {
        $this->postJson('/api/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        $this->postJson('/api/auth/register', $this->payload(['email' => 'no-es-un-email', 'password' => 'corta']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password'])
            ->assertJsonMissingValidationErrors(['name']);

        $this->assertDatabaseCount('users', 0);
    }
}
