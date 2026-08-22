<?php

namespace Tests\Feature\Catalog;

use App\Models\Character;
use App\Models\Episode;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharactersEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_los_personajes_paginados(): void
    {
        Character::factory()->count(25)->create();

        $this->getJson('/api/characters')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonStructure(['data' => [['id', 'external_id', 'name', 'status', 'species', 'type', 'gender', 'image', 'episodes_count']]]);

        $this->getJson('/api/characters?page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_permite_elegir_el_tamano_de_pagina(): void
    {
        Character::factory()->count(5)->create();

        $this->getJson('/api/characters?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_filtra_por_estado_especie_y_genero(): void
    {
        Character::factory()->create(['name' => 'Rick Sanchez', 'status' => 'Alive', 'species' => 'Human', 'gender' => 'Male']);
        Character::factory()->create(['name' => 'Summer Smith', 'status' => 'Alive', 'species' => 'Human', 'gender' => 'Female']);
        Character::factory()->create(['name' => 'Birdperson', 'status' => 'Dead', 'species' => 'Alien', 'gender' => 'Male']);

        $this->getJson('/api/characters?status=Alive')->assertJsonCount(2, 'data');
        $this->getJson('/api/characters?species=Alien')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Birdperson');
        $this->getJson('/api/characters?gender=Female')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Summer Smith');

        // Los filtros se combinan con AND.
        $this->getJson('/api/characters?status=Alive&gender=Male')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Rick Sanchez');
        $this->getJson('/api/characters?status=Dead&gender=Female')->assertJsonCount(0, 'data');
    }

    public function test_busca_por_nombre_parcial(): void
    {
        Character::factory()->create(['name' => 'Rick Sanchez']);
        Character::factory()->create(['name' => 'Evil Rick']);
        Character::factory()->create(['name' => 'Morty Smith']);

        $this->getJson('/api/characters?name=rick')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_los_comodines_de_sql_en_el_nombre_se_buscan_literalmente(): void
    {
        Character::factory()->create(['name' => 'Rick Sanchez']);
        Character::factory()->create(['name' => '100% Rick']);

        $this->getJson('/api/characters?name=%25')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', '100% Rick');
        $this->getJson('/api/characters?name=_')->assertJsonCount(0, 'data');
    }

    public function test_los_enlaces_de_paginacion_conservan_los_filtros(): void
    {
        Character::factory()->count(3)->create(['status' => 'Alive']);

        $this->getJson('/api/characters?status=Alive&per_page=2')
            ->assertOk()
            ->assertJsonPath('links.next', fn (string $url) => str_contains($url, 'status=Alive') && str_contains($url, 'page=2'));
    }

    public function test_rechaza_filtros_con_valores_no_validos(): void
    {
        $this->getJson('/api/characters?status=Zombie')->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->getJson('/api/characters?gender=Otro')->assertUnprocessable()->assertJsonValidationErrors(['gender']);
        $this->getJson('/api/characters?per_page=101')->assertUnprocessable()->assertJsonValidationErrors(['per_page']);
        $this->getJson('/api/characters?page=0')->assertUnprocessable()->assertJsonValidationErrors(['page']);
    }

    public function test_el_listado_incluye_las_localizaciones_pero_no_los_episodios(): void
    {
        $origin = Location::factory()->create(['name' => 'Earth (C-137)']);
        $character = Character::factory()->create(['origin_location_id' => $origin->id, 'current_location_id' => null]);
        $character->episodes()->attach(Episode::factory()->count(2)->create());

        $this->getJson('/api/characters')
            ->assertOk()
            ->assertJsonPath('data.0.origin.name', 'Earth (C-137)')
            ->assertJsonPath('data.0.current_location', null)
            ->assertJsonPath('data.0.episodes_count', 2)
            ->assertJsonMissingPath('data.0.episodes');
    }

    public function test_el_detalle_incluye_localizaciones_y_episodios(): void
    {
        $origin = Location::factory()->create();
        $current = Location::factory()->create();
        $character = Character::factory()->create(['origin_location_id' => $origin->id, 'current_location_id' => $current->id]);
        $episodes = Episode::factory()->count(2)->create();
        $character->episodes()->attach($episodes);

        $this->getJson("/api/characters/{$character->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $character->id)
            ->assertJsonPath('data.external_id', $character->external_id)
            ->assertJsonPath('data.origin.id', $origin->id)
            ->assertJsonPath('data.current_location.id', $current->id)
            ->assertJsonCount(2, 'data.episodes')
            ->assertJsonStructure(['data' => ['episodes' => [['id', 'name', 'code', 'air_date']]]]);
    }

    public function test_devuelve_404_si_el_personaje_no_existe(): void
    {
        $this->getJson('/api/characters/999')->assertNotFound();
    }
}
