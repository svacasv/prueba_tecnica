<?php

namespace Tests\Feature\Catalog;

use App\Models\Character;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationsEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_las_localizaciones_con_el_numero_de_residentes(): void
    {
        $earth = Location::factory()->create(['name' => 'Earth (C-137)']);
        Location::factory()->create(['name' => 'Abadango']);
        Character::factory()->count(2)->create(['current_location_id' => $earth->id]);

        $this->getJson('/api/locations')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Earth (C-137)')
            ->assertJsonPath('data.0.residents_count', 2)
            ->assertJsonPath('data.1.residents_count', 0)
            ->assertJsonMissingPath('data.0.residents');
    }

    public function test_filtra_por_tipo_dimension_y_nombre(): void
    {
        Location::factory()->create(['name' => 'Earth (C-137)', 'type' => 'Planet', 'dimension' => 'Dimension C-137']);
        Location::factory()->create(['name' => 'Citadel of Ricks', 'type' => 'Space station', 'dimension' => 'unknown']);
        Location::factory()->create(['name' => 'Abadango', 'type' => 'Cluster', 'dimension' => 'unknown']);

        $this->getJson('/api/locations?type=Planet')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Earth (C-137)');
        $this->getJson('/api/locations?dimension=unknown')->assertJsonCount(2, 'data');
        $this->getJson('/api/locations?name=citadel')->assertJsonCount(1, 'data');
        $this->getJson('/api/locations?dimension=unknown&type=Cluster')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Abadango');
    }

    public function test_el_detalle_incluye_solo_los_residentes_actuales(): void
    {
        $location = Location::factory()->create();
        $resident = Character::factory()->create(['current_location_id' => $location->id]);
        // Nació aquí pero ya no vive aquí: no es residente.
        Character::factory()->create(['origin_location_id' => $location->id]);

        $this->getJson("/api/locations/{$location->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.residents')
            ->assertJsonPath('data.residents.0.id', $resident->id);
    }

    public function test_devuelve_404_si_la_localizacion_no_existe(): void
    {
        $this->getJson('/api/locations/999')->assertNotFound();
    }
}
