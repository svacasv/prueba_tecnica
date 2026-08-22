<?php

namespace Tests\Unit\RickAndMorty\DTO;

use App\Services\RickAndMorty\DTO\CharacterData;
use App\Services\RickAndMorty\Exceptions\InvalidExternalDataException;
use Tests\TestCase;

class CharacterDataTest extends TestCase
{
    /**
     * Morty tal y como lo devuelve la API: origen "unknown" sin url y "type" vacío.
     */
    private function morty(): array
    {
        return $this->fixture('characters-page-1')['results'][0];
    }

    public function test_convierte_un_personaje_de_la_api_normalizando_los_campos(): void
    {
        $morty = CharacterData::fromArray($this->morty());

        $this->assertSame(2, $morty->externalId);
        $this->assertSame('Morty Smith', $morty->name);
        $this->assertSame('Alive', $morty->status);
        $this->assertSame('Human', $morty->species);
        $this->assertNull($morty->type, 'El "type" vacío debe quedar como null');
        $this->assertSame('Male', $morty->gender);
        $this->assertNull($morty->originExternalId, 'El origen "unknown" no tiene id');
        $this->assertSame(3, $morty->currentLocationExternalId);
        $this->assertCount(51, $morty->episodeExternalIds);
        $this->assertSame(1, $morty->episodeExternalIds[0]);
    }

    public function test_rechaza_un_personaje_sin_nombre(): void
    {
        $data = $this->morty();
        unset($data['name']);

        $this->expectException(InvalidExternalDataException::class);
        $this->expectExceptionMessage('personaje (id 2)');

        CharacterData::fromArray($data);
    }

    public function test_rechaza_un_personaje_sin_id(): void
    {
        $data = $this->morty();
        unset($data['id']);

        $this->expectException(InvalidExternalDataException::class);
        $this->expectExceptionMessage('sin id');

        CharacterData::fromArray($data);
    }

    public function test_rechaza_un_personaje_cuyo_origen_no_es_un_objeto(): void
    {
        $data = $this->morty();
        $data['origin'] = 'Earth';

        $this->expectException(InvalidExternalDataException::class);

        CharacterData::fromArray($data);
    }

    public function test_rechaza_un_personaje_con_la_lista_de_episodios_mal_formada(): void
    {
        $data = $this->morty();
        $data['episode'] = 'https://rickandmortyapi.com/api/episode/1';

        $this->expectException(InvalidExternalDataException::class);

        CharacterData::fromArray($data);
    }
}
