<?php

namespace Tests\Unit\RickAndMorty;

use App\Services\RickAndMorty\ExternalValueParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExternalValueParserTest extends TestCase
{
    #[DataProvider('cadenasVacias')]
    public function test_convierte_cadenas_vacias_en_null(?string $valor): void
    {
        $this->assertNull(ExternalValueParser::nullIfEmpty($valor));
    }

    public static function cadenasVacias(): array
    {
        return [
            'null' => [null],
            'vacía' => [''],
            'solo espacios' => ['   '],
        ];
    }

    public function test_conserva_los_valores_con_contenido(): void
    {
        $this->assertSame('Planet', ExternalValueParser::nullIfEmpty('Planet'));
        $this->assertSame('unknown', ExternalValueParser::nullIfEmpty('unknown'));
        $this->assertSame('Earth', ExternalValueParser::nullIfEmpty('  Earth  '));
    }

    #[DataProvider('urlsDeRecursos')]
    public function test_extrae_el_id_de_una_url_de_recurso(?string $url, ?int $esperado): void
    {
        $this->assertSame($esperado, ExternalValueParser::idFromUrl($url));
    }

    public static function urlsDeRecursos(): array
    {
        return [
            'episodio' => ['https://rickandmortyapi.com/api/episode/12', 12],
            'localización' => ['https://rickandmortyapi.com/api/location/3', 3],
            'con barra final' => ['https://rickandmortyapi.com/api/character/826/', 826],
            'url vacía (unknown)' => ['', null],
            'null' => [null, null],
            'sin id' => ['https://rickandmortyapi.com/api/episode', null],
            'id no numérico' => ['https://rickandmortyapi.com/api/episode/abc', null],
            'texto cualquiera' => ['hola', null],
        ];
    }

    public function test_extrae_varios_ids_descartando_las_urls_que_no_entiende(): void
    {
        $ids = ExternalValueParser::idsFromUrls([
            'https://rickandmortyapi.com/api/episode/1',
            'https://rickandmortyapi.com/api/episode/2',
            'https://rickandmortyapi.com/api/episode/2', // repetida
            '',
            null,
            42, // ni siquiera es una cadena
            'https://rickandmortyapi.com/api/episode/3',
        ]);

        $this->assertSame([1, 2, 3], $ids);
    }

    public function test_interpreta_las_fechas_con_el_formato_de_la_api(): void
    {
        $fecha = ExternalValueParser::dateOrNull('December 2, 2013');

        $this->assertNotNull($fecha);
        $this->assertSame('2013-12-02', $fecha->toDateString());
    }

    #[DataProvider('fechasNoValidas')]
    public function test_devuelve_null_si_la_fecha_no_se_puede_interpretar(?string $valor): void
    {
        $this->assertNull(ExternalValueParser::dateOrNull($valor));
    }

    public static function fechasNoValidas(): array
    {
        return [
            'null' => [null],
            'vacía' => [''],
            'otro formato' => ['2013-12-02'],
            'texto' => ['unknown'],
        ];
    }
}
