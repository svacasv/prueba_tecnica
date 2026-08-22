<?php

namespace Tests\Feature\Favorites;

use App\Models\Character;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoritesEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_anade_un_personaje_a_favoritos(): void
    {
        $user = User::factory()->create();
        $character = Character::factory()->create(['name' => 'Birdperson']);

        $this->actingAsApiUser($user)
            ->postJson('/api/favorites', ['character_id' => $character->id])
            ->assertCreated()
            ->assertJsonPath('data.id', $character->id)
            ->assertJsonPath('data.name', 'Birdperson');

        $this->assertDatabaseHas('favorites', ['user_id' => $user->id, 'character_id' => $character->id]);
    }

    public function test_no_permite_anadir_el_mismo_personaje_dos_veces(): void
    {
        $user = User::factory()->create();
        $character = Character::factory()->create();
        $user->favoriteCharacters()->attach($character);

        $this->actingAsApiUser($user)
            ->postJson('/api/favorites', ['character_id' => $character->id])
            ->assertConflict()
            ->assertJsonPath('message', 'The character is already in your favorites.');

        $this->assertDatabaseCount('favorites', 1);
    }

    public function test_rechaza_un_personaje_que_no_existe(): void
    {
        $this->actingAsApiUser(User::factory()->create())
            ->postJson('/api/favorites', ['character_id' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['character_id']);

        $this->actingAsApiUser(User::factory()->create())
            ->postJson('/api/favorites', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['character_id']);
    }

    public function test_lista_solo_los_favoritos_del_usuario_con_los_mas_recientes_primero(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        [$first, $second, $someoneElses] = Character::factory()->count(3)->create();

        $user->favoriteCharacters()->attach($first, ['created_at' => now()->subDay()]);
        $user->favoriteCharacters()->attach($second, ['created_at' => now()]);
        $other->favoriteCharacters()->attach($someoneElses);

        $this->actingAsApiUser($user)
            ->getJson('/api/favorites')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.1.id', $first->id)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure(['data' => [['id', 'name', 'status', 'origin', 'current_location', 'episodes_count', 'favorited_at']]]);
    }

    public function test_el_listado_se_pagina(): void
    {
        $user = User::factory()->create();
        $user->favoriteCharacters()->attach(Character::factory()->count(3)->create());

        $this->actingAsApiUser($user)
            ->getJson('/api/favorites?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.last_page', 2);

        $this->actingAsApiUser($user)
            ->getJson('/api/favorites?per_page=0')
            ->assertUnprocessable();
    }

    public function test_elimina_un_personaje_de_favoritos(): void
    {
        $user = User::factory()->create();
        $character = Character::factory()->create();
        $user->favoriteCharacters()->attach($character);

        $this->actingAsApiUser($user)
            ->deleteJson("/api/favorites/{$character->id}")
            ->assertNoContent();

        $this->assertDatabaseCount('favorites', 0);
        // El personaje en sí no se borra, solo la fila de favoritos.
        $this->assertDatabaseHas('characters', ['id' => $character->id]);
    }

    public function test_eliminar_un_personaje_que_no_esta_en_favoritos_devuelve_404(): void
    {
        $user = User::factory()->create();
        $character = Character::factory()->create();

        $this->actingAsApiUser($user)
            ->deleteJson("/api/favorites/{$character->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'The character is not in your favorites.');

        $this->actingAsApiUser($user)
            ->deleteJson('/api/favorites/999')
            ->assertNotFound();
    }

    public function test_un_usuario_no_puede_eliminar_los_favoritos_de_otro(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $character = Character::factory()->create();
        $owner->favoriteCharacters()->attach($character);

        $this->actingAsApiUser($intruder)
            ->deleteJson("/api/favorites/{$character->id}")
            ->assertNotFound();

        $this->assertDatabaseCount('favorites', 1);
    }

    public function test_todas_las_rutas_requieren_token(): void
    {
        $character = Character::factory()->create();

        $this->getJson('/api/favorites')->assertUnauthorized();
        $this->postJson('/api/favorites', ['character_id' => $character->id])->assertUnauthorized();
        $this->deleteJson("/api/favorites/{$character->id}")->assertUnauthorized();
    }

    public function test_si_se_borra_el_personaje_desaparece_de_los_favoritos(): void
    {
        $user = User::factory()->create();
        $character = Character::factory()->create();
        $user->favoriteCharacters()->attach($character);

        $character->delete();

        $this->assertDatabaseCount('favorites', 0);
    }
}
