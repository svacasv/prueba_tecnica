<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Todas las respuestas de error de la API tienen la misma forma:
 * {"message": "..."} y, solo en validación, "errors". Nunca una traza.
 */
class ErrorFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_ruta_inexistente(): void
    {
        // Mensaje propio de Laravel: indica la ruta pedida, nada interno.
        $this->getJson('/api/no-existe')
            ->assertNotFound()
            ->assertExactJson(['message' => 'The route api/no-existe could not be found.']);
    }

    public function test_registro_inexistente_no_revela_el_nombre_del_modelo(): void
    {
        $this->getJson('/api/characters/999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Not Found.']);
    }

    public function test_metodo_no_permitido(): void
    {
        // Laravel ya trae un mensaje útil (indica los métodos permitidos); se conserva.
        $response = $this->postJson('/api/characters')
            ->assertMethodNotAllowed()
            ->assertJsonPath('message', fn (string $message) => str_starts_with($message, 'The POST method is not supported'));

        $this->assertSame(['message'], array_keys($response->json()));
    }

    public function test_sin_autenticar(): void
    {
        $this->getJson('/api/favorites')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_validacion_incluye_los_errores_por_campo(): void
    {
        $response = $this->postJson('/api/auth/register', ['email' => 'no-es-un-email'])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['name', 'email', 'password']]);

        $this->assertSame(['message', 'errors'], array_keys($response->json()));
    }

    public function test_conflicto_con_mensaje_propio(): void
    {
        $character = Character::factory()->create();
        $user = User::factory()->create();
        $user->favoriteCharacters()->attach($character);

        $this->actingAsApiUser($user)
            ->postJson('/api/favorites', ['character_id' => $character->id])
            ->assertConflict()
            ->assertExactJson(['message' => 'The character is already in your favorites.']);
    }

    public function test_demasiadas_peticiones(): void
    {
        $credentials = ['email' => 'nadie@example.com', 'password' => 'lo-que-sea'];

        foreach (range(1, 10) as $attempt) {
            $this->postJson('/api/auth/login', $credentials);
        }

        $this->postJson('/api/auth/login', $credentials)
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertExactJson(['message' => 'Too Many Attempts.']);
    }

    public function test_un_error_interno_no_expone_detalles_aunque_debug_este_activo(): void
    {
        config()->set('app.debug', true);
        Route::get('/api/_prueba-error', fn () => throw new RuntimeException('detalle interno secreto'));

        $response = $this->getJson('/api/_prueba-error')
            ->assertServerError()
            ->assertExactJson(['message' => 'Internal Server Error.']);

        $this->assertStringNotContainsString('secreto', $response->getContent());
    }

    public function test_las_rutas_web_no_se_ven_afectadas(): void
    {
        $this->get('/no-existe')->assertNotFound()->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }
}
