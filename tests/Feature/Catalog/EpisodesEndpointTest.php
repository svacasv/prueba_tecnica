<?php

namespace Tests\Feature\Catalog;

use App\Models\Character;
use App\Models\Episode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EpisodesEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_los_episodios_ordenados_por_codigo_con_el_numero_de_personajes(): void
    {
        $pilot = Episode::factory()->create(['code' => 'S01E01', 'name' => 'Pilot']);
        Episode::factory()->create(['code' => 'S02E01']);
        Episode::factory()->create(['code' => 'S01E02']);
        $pilot->characters()->attach(Character::factory()->count(3)->create());

        $this->getJson('/api/episodes')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.code', 'S01E01')
            ->assertJsonPath('data.0.characters_count', 3)
            ->assertJsonPath('data.1.code', 'S01E02')
            ->assertJsonPath('data.2.code', 'S02E01')
            ->assertJsonMissingPath('data.0.characters');
    }

    public function test_filtra_por_codigo_temporada_y_nombre(): void
    {
        Episode::factory()->create(['code' => 'S01E01', 'name' => 'Pilot']);
        Episode::factory()->create(['code' => 'S01E02', 'name' => 'Lawnmower Dog']);
        Episode::factory()->create(['code' => 'S03E07', 'name' => 'The Ricklantis Mixup']);

        $this->getJson('/api/episodes?code=s01e02')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Lawnmower Dog');
        $this->getJson('/api/episodes?season=1')->assertJsonCount(2, 'data');
        $this->getJson('/api/episodes?season=3')->assertJsonCount(1, 'data');
        $this->getJson('/api/episodes?name=rick')->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'S03E07');
    }

    public function test_rechaza_filtros_no_validos(): void
    {
        $this->getJson('/api/episodes?code=episodio-1')->assertUnprocessable()->assertJsonValidationErrors(['code']);
        $this->getJson('/api/episodes?season=0')->assertUnprocessable()->assertJsonValidationErrors(['season']);
    }

    public function test_el_detalle_incluye_los_personajes(): void
    {
        $episode = Episode::factory()->create(['air_date' => '2013-12-02']);
        $episode->characters()->attach(Character::factory()->count(2)->create());

        $this->getJson("/api/episodes/{$episode->id}")
            ->assertOk()
            ->assertJsonPath('data.air_date', '2013-12-02')
            ->assertJsonCount(2, 'data.characters')
            ->assertJsonStructure(['data' => ['characters' => [['id', 'name', 'status', 'image']]]]);
    }

    public function test_devuelve_404_si_el_episodio_no_existe(): void
    {
        $this->getJson('/api/episodes/999')->assertNotFound();
    }
}
