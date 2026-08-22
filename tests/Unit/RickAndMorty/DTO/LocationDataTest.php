<?php

namespace Tests\Unit\RickAndMorty\DTO;

use App\Services\RickAndMorty\DTO\LocationData;
use App\Services\RickAndMorty\Exceptions\InvalidExternalDataException;
use Tests\TestCase;

class LocationDataTest extends TestCase
{
    public function test_convierte_una_localizacion_de_la_api(): void
    {
        $earth = LocationData::fromArray($this->fixture('locations-page-1')['results'][0]);

        $this->assertSame(1, $earth->externalId);
        $this->assertSame('Earth (C-137)', $earth->name);
        $this->assertSame('Planet', $earth->type);
        $this->assertSame('Dimension C-137', $earth->dimension);
    }

    public function test_un_tipo_vacio_queda_como_null_y_unknown_se_conserva(): void
    {
        $results = $this->fixture('locations-page-1')['results'];

        $citadel = LocationData::fromArray($results[1]);
        $spaceTahoe = LocationData::fromArray($results[2]);

        // "unknown" es un valor que la API usa a propósito: se conserva tal cual.
        $this->assertSame('unknown', $citadel->dimension);
        // La cadena vacía significa "sin dato": se normaliza a null.
        $this->assertNull($spaceTahoe->type);
    }

    public function test_rechaza_una_localizacion_con_id_no_numerico(): void
    {
        $this->expectException(InvalidExternalDataException::class);

        LocationData::fromArray(['id' => 'uno', 'name' => 'Earth', 'type' => '', 'dimension' => '']);
    }
}
