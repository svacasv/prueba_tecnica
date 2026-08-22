<?php

namespace Tests\Feature\RickAndMorty;

use App\Services\RickAndMorty\DTO\CharacterData;
use App\Services\RickAndMorty\Exceptions\InvalidExternalDataException;
use App\Services\RickAndMorty\Exceptions\RickAndMortyApiException;
use App\Services\RickAndMorty\ResponseParser;
use App\Services\RickAndMorty\RickAndMortyClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Todas las peticiones se simulan con Http::fake(): ningún test toca la red.
 */
class RickAndMortyClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Los reintentos esperan entre intentos; en los tests esa espera se simula.
        Sleep::fake();
    }

    private function client(int $maxAttempts = 3): RickAndMortyClient
    {
        return new RickAndMortyClient(
            parser: new ResponseParser,
            baseUrl: 'https://rickandmortyapi.com/api',
            timeoutSeconds: 5,
            maxAttempts: $maxAttempts,
            retryDelayMs: 100,
        );
    }

    public function test_descarga_una_pagina_de_personajes_y_la_devuelve_como_dtos(): void
    {
        Http::fake([
            'rickandmortyapi.com/api/character*' => Http::response($this->fixture('characters-page-1')),
        ]);

        $page = $this->client()->characters(1);

        $this->assertSame(42, $page->totalPages);
        $this->assertCount(3, $page->items);
        $this->assertInstanceOf(CharacterData::class, $page->items[0]);
        $this->assertSame('Morty Smith', $page->items[0]->name);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://rickandmortyapi.com/api/character?page=1'
                && $request->hasHeader('Accept', 'application/json');
        });
    }

    public function test_descarga_episodios_y_localizaciones_de_sus_propios_recursos(): void
    {
        Http::fake([
            'rickandmortyapi.com/api/episode*' => Http::response($this->fixture('episodes-page-1')),
            'rickandmortyapi.com/api/location*' => Http::response($this->fixture('locations-page-1')),
        ]);

        $episodes = $this->client()->episodes(2);
        $locations = $this->client()->locations(3);

        $this->assertSame('S01E01', $episodes->items[0]->code);
        $this->assertSame('Earth (C-137)', $locations->items[0]->name);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://rickandmortyapi.com/api/episode?page=2');
        Http::assertSent(fn (Request $request) => $request->url() === 'https://rickandmortyapi.com/api/location?page=3');
    }

    public function test_reintenta_ante_un_error_del_servidor_y_devuelve_la_pagina_cuando_se_recupera(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push('Internal Server Error', 500)
                ->push($this->fixture('characters-page-1'), 200),
        ]);

        $page = $this->client()->characters(1);

        $this->assertCount(3, $page->items);
        Http::assertSentCount(2);
        Sleep::assertSleptTimes(1);
    }

    public function test_reintenta_ante_el_limite_de_peticiones_y_falla_de_forma_controlada_si_persiste(): void
    {
        // Respuesta real de Cloudflare cuando se supera el límite: texto plano, no JSON.
        Http::fake(['*' => Http::response('error code: 1015', 429)]);

        try {
            $this->client(maxAttempts: 3)->characters(27);
            $this->fail('Se esperaba una RickAndMortyApiException');
        } catch (RickAndMortyApiException $exception) {
            $this->assertStringContainsString('429', $exception->getMessage());
            $this->assertStringContainsString('página 27', $exception->getMessage());
        }

        Http::assertSentCount(3);
        Sleep::assertSleptTimes(2);
    }

    public function test_no_reintenta_cuando_la_pagina_no_existe(): void
    {
        Http::fake(['*' => Http::response(['error' => 'There is nothing here'], 404)]);

        try {
            $this->client()->characters(999);
            $this->fail('Se esperaba una RickAndMortyApiException');
        } catch (RickAndMortyApiException $exception) {
            $this->assertStringContainsString('404', $exception->getMessage());
        }

        Http::assertSentCount(1);
        Sleep::assertNeverSlept();
    }

    public function test_falla_de_forma_controlada_ante_un_error_de_conexion(): void
    {
        Http::fake(['*' => Http::failedConnection('cURL error 28: Operation timed out')]);

        $this->expectException(RickAndMortyApiException::class);
        $this->expectExceptionMessage('No se pudo conectar');

        $this->client()->characters(1);
    }

    public function test_falla_si_la_respuesta_no_es_json_aunque_el_codigo_sea_200(): void
    {
        Http::fake(['*' => Http::response('<html>Mantenimiento</html>', 200)]);

        $this->expectException(RickAndMortyApiException::class);
        $this->expectExceptionMessage('no es un JSON válido');

        $this->client()->characters(1);
    }

    public function test_falla_si_el_json_no_tiene_la_estructura_de_una_pagina(): void
    {
        Http::fake(['*' => Http::response(['unexpected' => 'shape'], 200)]);

        $this->expectException(InvalidExternalDataException::class);

        $this->client()->characters(1);
    }

    public function test_el_cliente_del_contenedor_se_construye_con_la_configuracion(): void
    {
        config()->set('services.rickandmorty.base_url', 'https://ejemplo.test/api/');
        Http::fake(['ejemplo.test/api/character*' => Http::response($this->fixture('characters-page-1'))]);

        $page = app(RickAndMortyClient::class)->characters(1);

        $this->assertCount(3, $page->items);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://ejemplo.test/api/character?page=1');
    }
}
