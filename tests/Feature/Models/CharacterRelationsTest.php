<?php

namespace Tests\Feature\Models;

use App\Models\Character;
use App\Models\Episode;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_personaje_puede_no_tener_localizaciones(): void
    {
        $character = Character::factory()->create();

        $this->assertNull($character->origin);
        $this->assertNull($character->currentLocation);
    }

    public function test_un_personaje_referencia_origen_y_ubicacion_actual(): void
    {
        $origin = Location::factory()->create();
        $current = Location::factory()->create();

        $character = Character::factory()->create([
            'origin_location_id' => $origin->id,
            'current_location_id' => $current->id,
        ]);

        $this->assertTrue($character->origin->is($origin));
        $this->assertTrue($character->currentLocation->is($current));
    }

    public function test_los_residentes_se_derivan_de_la_ubicacion_actual(): void
    {
        $location = Location::factory()->create();

        $resident = Character::factory()->create(['current_location_id' => $location->id]);

        // Este personaje nació en la localización pero ya no está allí: no es residente.
        Character::factory()->create(['origin_location_id' => $location->id]);

        $this->assertCount(1, $location->residents);
        $this->assertTrue($location->residents->first()->is($resident));
    }

    public function test_la_relacion_con_episodios_no_se_duplica(): void
    {
        $character = Character::factory()->create();
        $episode = Episode::factory()->create();

        // syncWithoutDetaching es lo que usará la sincronización: se puede repetir
        // tantas veces como haga falta sin crear filas duplicadas en la pivote.
        $character->episodes()->syncWithoutDetaching([$episode->id]);
        $character->episodes()->syncWithoutDetaching([$episode->id]);

        $this->assertCount(1, $character->fresh()->episodes);
        $this->assertCount(1, $episode->fresh()->characters);
    }

    public function test_al_borrar_una_localizacion_el_personaje_se_queda_sin_ella(): void
    {
        $location = Location::factory()->create();
        $character = Character::factory()->create(['current_location_id' => $location->id]);

        $location->delete();

        $this->assertNull($character->fresh()->current_location_id);
    }
}
