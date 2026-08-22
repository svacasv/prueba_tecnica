<?php

namespace Tests\Feature\Sync;

use App\Models\Character;
use App\Models\Episode;
use App\Models\Location;
use App\Models\RawApiPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * La API se simula siempre con Http::fake(). Las fixtures traen 3 localizaciones
 * (ids 1, 3 y 118), 2 episodios (1 y 2) y 3 personajes (Morty = 2, 6 y 8).
 */
class SyncRickAndMortyCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ni la pausa entre páginas ni la de los reintentos esperan de verdad.
        Sleep::fake();
    }

    /**
     * Una página de fixture con el número total de páginas que interese al test.
     */
    private function page(string $fixture, int $totalPages = 1): array
    {
        $json = $this->fixture($fixture);
        $json['info']['pages'] = $totalPages;

        return $json;
    }

    private function fakeWholeApi(): void
    {
        Http::fake([
            '*/api/location*' => Http::response($this->page('locations-page-1')),
            '*/api/episode*' => Http::response($this->page('episodes-page-1')),
            '*/api/character*' => Http::response($this->page('characters-page-1')),
        ]);
    }

    public function test_sincroniza_las_tres_entidades_y_enlaza_sus_relaciones(): void
    {
        $this->fakeWholeApi();

        $this->artisan('rickandmorty:sync')
            ->expectsOutputToContain('Sincronización completada.')
            ->assertSuccessful();

        $this->assertDatabaseCount('locations', 3);
        $this->assertDatabaseCount('episodes', 2);
        $this->assertDatabaseCount('characters', 3);

        $morty = Character::query()->where('external_id', 2)->firstOrFail();

        $this->assertNull($morty->origin, 'El origen de Morty es "unknown" en la API');
        $this->assertSame('Citadel of Ricks', $morty->currentLocation->name);
        $this->assertNull($morty->type);

        // Morty aparece en 51 episodios, pero solo los 2 de la fixture existen en la base de datos.
        $this->assertSame(['S01E01', 'S01E02'], $morty->episodes->pluck('code')->sort()->values()->all());

        $pilot = Episode::query()->where('code', 'S01E01')->firstOrFail();
        $this->assertSame('2013-12-02', $pilot->air_date->toDateString());

        $spaceTahoe = Location::query()->where('external_id', 118)->firstOrFail();
        $this->assertNull($spaceTahoe->type);
    }

    public function test_respeta_el_orden_localizaciones_episodios_personajes(): void
    {
        $this->fakeWholeApi();

        $this->artisan('rickandmorty:sync', ['--only' => ['characters', 'locations', 'episodes']])
            ->assertSuccessful();

        $requested = collect(Http::recorded())
            ->map(fn (array $pair) => $pair[0])
            ->map(fn (Request $request) => basename(parse_url($request->url(), PHP_URL_PATH)))
            ->all();

        $this->assertSame(['location', 'episode', 'character'], $requested);
    }

    public function test_guarda_el_json_crudo_de_cada_pagina_antes_de_procesarla(): void
    {
        $this->fakeWholeApi();

        $this->artisan('rickandmorty:sync')->assertSuccessful();

        $this->assertDatabaseCount('raw_api_pages', 3);

        $raw = RawApiPage::query()->where('entity', 'characters')->where('page', 1)->firstOrFail();

        // assertEquals y no assertSame: MySQL guarda el JSON en binario y devuelve
        // las claves en otro orden, pero el contenido es el mismo.
        $this->assertEquals($this->page('characters-page-1'), $raw->payload);
        $this->assertNotNull($raw->fetched_at);
    }

    public function test_ejecutarlo_dos_veces_no_duplica_registros_ni_relaciones(): void
    {
        $this->fakeWholeApi();

        $this->artisan('rickandmorty:sync')->assertSuccessful();
        $this->artisan('rickandmorty:sync')->assertSuccessful();

        $this->assertDatabaseCount('locations', 3);
        $this->assertDatabaseCount('episodes', 2);
        $this->assertDatabaseCount('characters', 3);
        $this->assertDatabaseCount('character_episode', 2);
        $this->assertDatabaseCount('raw_api_pages', 3);
    }

    public function test_una_segunda_ejecucion_actualiza_los_cambios_de_la_fuente(): void
    {
        // Entre la primera y la segunda descarga la API cambia: Morty pasa a estar
        // muerto y ya no aparece en el episodio 1.
        $changed = $this->page('characters-page-1');
        $changed['results'][0]['status'] = 'Dead';
        $changed['results'][0]['episode'] = ['https://rickandmortyapi.com/api/episode/2'];

        Http::fake([
            '*/api/location*' => Http::response($this->page('locations-page-1')),
            '*/api/episode*' => Http::response($this->page('episodes-page-1')),
            '*/api/character*' => Http::sequence()
                ->push($this->page('characters-page-1'))
                ->push($changed),
        ]);

        $this->artisan('rickandmorty:sync')->assertSuccessful();
        $this->artisan('rickandmorty:sync', ['--only' => ['characters']])->assertSuccessful();

        $morty = Character::query()->where('external_id', 2)->firstOrFail();

        $this->assertSame('Dead', $morty->status);
        $this->assertSame(['S01E02'], $morty->episodes->pluck('code')->all());
        $this->assertDatabaseCount('characters', 3);
    }

    public function test_recorre_todas_las_paginas_y_hace_una_pausa_entre_ellas(): void
    {
        $secondPage = $this->page('locations-page-1', totalPages: 2);
        foreach ($secondPage['results'] as &$location) {
            $location['id'] += 1000;
        }

        Http::fake([
            '*/api/location?page=1' => Http::response($this->page('locations-page-1', totalPages: 2)),
            '*/api/location?page=2' => Http::response($secondPage),
        ]);

        $this->artisan('rickandmorty:sync', ['--only' => ['locations']])->assertSuccessful();

        $this->assertDatabaseCount('locations', 6);
        Http::assertSentCount(2);
        Sleep::assertSleptTimes(1);
    }

    public function test_si_una_pagina_falla_continua_con_las_siguientes_y_termina_con_error(): void
    {
        $thirdPage = $this->page('locations-page-1', totalPages: 3);
        foreach ($thirdPage['results'] as &$location) {
            $location['id'] += 1000;
        }

        Http::fake([
            '*/api/location?page=1' => Http::response($this->page('locations-page-1', totalPages: 3)),
            '*/api/location?page=2' => Http::response('error code: 1015', 429),
            '*/api/location?page=3' => Http::response($thirdPage),
        ]);

        $this->artisan('rickandmorty:sync', ['--only' => ['locations']])
            ->expectsOutputToContain('locations, página 2: La API respondió con el código 429')
            ->expectsOutputToContain('terminó con errores')
            ->assertFailed();

        // Las páginas 1 y 3 se han guardado aunque la 2 fallara.
        $this->assertDatabaseCount('locations', 6);
        $this->assertDatabaseCount('raw_api_pages', 2);
    }

    public function test_un_registro_mal_formado_se_descarta_y_se_avisa_sin_fallar(): void
    {
        $characters = $this->page('characters-page-1');
        unset($characters['results'][1]['name']);

        Http::fake(['*/api/character*' => Http::response($characters)]);

        $this->artisan('rickandmorty:sync', ['--only' => ['characters']])
            ->expectsOutputToContain('personaje (id 6) descartado')
            ->assertSuccessful();

        $this->assertDatabaseCount('characters', 2);
    }

    public function test_sin_localizaciones_ni_episodios_los_personajes_se_guardan_sin_enlaces(): void
    {
        Http::fake(['*/api/character*' => Http::response($this->page('characters-page-1'))]);

        $this->artisan('rickandmorty:sync', ['--only' => ['characters']])
            ->expectsOutputToContain('no hay episodios en la base de datos')
            ->assertSuccessful();

        $morty = Character::query()->where('external_id', 2)->firstOrFail();

        $this->assertNull($morty->current_location_id);
        $this->assertDatabaseCount('character_episode', 0);
    }

    public function test_reprocesa_desde_el_json_crudo_sin_tocar_la_red(): void
    {
        $this->fakeWholeApi();
        $this->artisan('rickandmorty:sync')->assertSuccessful();

        // Se vacían las tablas de dominio pero se conserva el JSON crudo.
        Character::query()->delete();
        Episode::query()->delete();
        Location::query()->delete();
        Http::fake();

        $this->artisan('rickandmorty:sync', ['--from-raw' => true])
            ->expectsOutputToContain('Reprocesando el JSON crudo')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertDatabaseCount('locations', 3);
        $this->assertDatabaseCount('episodes', 2);
        $this->assertDatabaseCount('characters', 3);
        $this->assertDatabaseCount('character_episode', 2);
    }

    public function test_reprocesar_sin_json_guardado_avisa_y_no_hace_nada(): void
    {
        Http::fake();

        $this->artisan('rickandmorty:sync', ['--from-raw' => true, '--only' => ['episodes']])
            ->expectsOutputToContain('no hay JSON crudo guardado')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertDatabaseCount('episodes', 0);
    }

    public function test_rechaza_una_entidad_desconocida(): void
    {
        Http::fake();

        $this->artisan('rickandmorty:sync', ['--only' => ['planetas']])
            ->expectsOutputToContain('Entidad desconocida: planetas')
            ->assertExitCode(2);

        Http::assertNothingSent();
    }
}
