<?php

namespace Tests\Unit\RickAndMorty;

use App\Services\RickAndMorty\DTO\CharacterData;
use App\Services\RickAndMorty\DTO\EpisodeData;
use App\Services\RickAndMorty\DTO\LocationData;
use App\Services\RickAndMorty\Exceptions\InvalidExternalDataException;
use App\Services\RickAndMorty\ResponseParser;
use Tests\TestCase;

class ResponseParserTest extends TestCase
{
    private ResponseParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new ResponseParser;
    }

    public function test_convierte_una_pagina_de_personajes(): void
    {
        $page = $this->parser->charactersPage($this->fixture('characters-page-1'));

        $this->assertSame(42, $page->totalPages);
        $this->assertCount(3, $page->items);
        $this->assertContainsOnlyInstancesOf(CharacterData::class, $page->items);
        $this->assertSame([], $page->rejected);
    }

    public function test_convierte_una_pagina_de_episodios(): void
    {
        $page = $this->parser->episodesPage($this->fixture('episodes-page-1'));

        $this->assertSame(3, $page->totalPages);
        $this->assertCount(2, $page->items);
        $this->assertContainsOnlyInstancesOf(EpisodeData::class, $page->items);
    }

    public function test_convierte_una_pagina_de_localizaciones(): void
    {
        $page = $this->parser->locationsPage($this->fixture('locations-page-1'));

        $this->assertSame(7, $page->totalPages);
        $this->assertCount(3, $page->items);
        $this->assertContainsOnlyInstancesOf(LocationData::class, $page->items);
    }

    public function test_conserva_el_json_original_para_poder_guardarlo_sin_procesar(): void
    {
        $json = $this->fixture('locations-page-1');

        $page = $this->parser->locationsPage($json);

        $this->assertSame($json, $page->raw);
    }

    public function test_un_registro_mal_formado_se_descarta_sin_perder_el_resto_de_la_pagina(): void
    {
        $json = $this->fixture('characters-page-1');
        unset($json['results'][1]['name']);     // personaje sin nombre
        $json['results'][] = 'esto no es un objeto';

        $page = $this->parser->charactersPage($json);

        $this->assertCount(2, $page->items);
        $this->assertCount(2, $page->rejected);
        $this->assertStringContainsString('personaje (id 6)', $page->rejected[0]);
        $this->assertStringContainsString('posición 3', $page->rejected[1]);
    }

    public function test_rechaza_una_pagina_sin_la_estructura_info_y_results(): void
    {
        $this->expectException(InvalidExternalDataException::class);
        $this->expectExceptionMessage('formato esperado');

        $this->parser->charactersPage(['error' => 'There is nothing here']);
    }

    public function test_rechaza_una_pagina_cuyo_numero_de_paginas_no_es_valido(): void
    {
        $json = $this->fixture('characters-page-1');
        $json['info']['pages'] = 'muchas';

        $this->expectException(InvalidExternalDataException::class);

        $this->parser->charactersPage($json);
    }
}
