<?php

namespace Tests\Unit\RickAndMorty\DTO;

use App\Services\RickAndMorty\DTO\EpisodeData;
use App\Services\RickAndMorty\Exceptions\InvalidExternalDataException;
use Tests\TestCase;

class EpisodeDataTest extends TestCase
{
    private function pilot(): array
    {
        return $this->fixture('episodes-page-1')['results'][0];
    }

    public function test_convierte_un_episodio_de_la_api(): void
    {
        $pilot = EpisodeData::fromArray($this->pilot());

        $this->assertSame(1, $pilot->externalId);
        $this->assertSame('Pilot', $pilot->name);
        $this->assertSame('S01E01', $pilot->code);
        $this->assertSame('2013-12-02', $pilot->airDate?->toDateString());
        $this->assertCount(19, $pilot->characterExternalIds);
    }

    public function test_una_fecha_que_no_se_entiende_queda_como_null_sin_rechazar_el_episodio(): void
    {
        $data = $this->pilot();
        $data['air_date'] = 'algún día';

        $episode = EpisodeData::fromArray($data);

        $this->assertNull($episode->airDate);
    }

    public function test_rechaza_un_codigo_de_episodio_con_formato_incorrecto(): void
    {
        $data = $this->pilot();
        $data['episode'] = 'Temporada 1, capítulo 1';

        $this->expectException(InvalidExternalDataException::class);
        $this->expectExceptionMessage('episodio (id 1)');

        EpisodeData::fromArray($data);
    }
}
