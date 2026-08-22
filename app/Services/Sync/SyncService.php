<?php

namespace App\Services\Sync;

use App\Models\Character;
use App\Models\Episode;
use App\Models\Location;
use App\Models\RawApiPage;
use App\Services\RickAndMorty\DTO\CharacterData;
use App\Services\RickAndMorty\DTO\EpisodeData;
use App\Services\RickAndMorty\DTO\LocationData;
use App\Services\RickAndMorty\DTO\PageData;
use App\Services\RickAndMorty\Exceptions\InvalidExternalDataException;
use App\Services\RickAndMorty\Exceptions\RickAndMortyApiException;
use App\Services\RickAndMorty\ResponseParser;
use App\Services\RickAndMorty\RickAndMortyClient;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Sincroniza la API de Rick and Morty con la base de datos local.
 *
 * Recorre las páginas de cada entidad, guarda el JSON crudo, y hace "upsert"
 * de los registros por su identificador externo. Ejecutarlo varias veces deja
 * la base de datos igual que ejecutarlo una: no duplica ni rompe relaciones.
 */
final class SyncService
{
    /**
     * Orden obligatorio: los personajes referencian localizaciones y episodios,
     * así que esas dos entidades tienen que existir antes.
     */
    public const ENTITIES = ['locations', 'episodes', 'characters'];

    public function __construct(
        private readonly RickAndMortyClient $client,
        private readonly ResponseParser $parser,
        private readonly int $delayBetweenPagesMs,
    ) {}

    /**
     * @param  list<string>  $entities  Subconjunto de self::ENTITIES.
     * @param  bool  $fromRaw  Reprocesar el JSON guardado en vez de descargar.
     * @param  Closure(string): void|null  $onProgress  Recibe una línea de progreso por página.
     */
    public function run(array $entities, bool $fromRaw = false, ?Closure $onProgress = null): SyncReport
    {
        $report = new SyncReport;
        $onProgress ??= fn (string $line) => null;

        // Se recorre ENTITIES (y no $entities) para respetar siempre el orden.
        foreach (self::ENTITIES as $entity) {
            if (! in_array($entity, $entities, strict: true)) {
                continue;
            }

            $fromRaw
                ? $this->syncFromRaw($entity, $report, $onProgress)
                : $this->syncFromApi($entity, $report, $onProgress);
        }

        return $report;
    }

    /**
     * Descarga página a página. Si una falla se anota y se continúa con la
     * siguiente: un fallo parcial no tira la sincronización entera.
     */
    private function syncFromApi(string $entity, SyncReport $report, Closure $onProgress): void
    {
        $page = 1;
        $totalPages = 1; // se conoce al recibir la primera página

        while ($page <= $totalPages) {
            try {
                $pageData = $this->download($entity, $page);
                $totalPages = $pageData->totalPages;

                $this->storeRaw($entity, $page, $pageData);
                $this->persist($entity, $page, $pageData, $report);

                $onProgress("{$entity}: página {$page}/{$totalPages} ({$this->count($pageData)})");
            } catch (RickAndMortyApiException|InvalidExternalDataException $exception) {
                $report->pageFailed($entity, $page, $exception->getMessage());
                Log::warning("Sincronización: fallo en {$entity} página {$page}", ['reason' => $exception->getMessage()]);

                $onProgress("{$entity}: página {$page} FALLÓ");
            }

            $page++;

            if ($page <= $totalPages) {
                Sleep::for($this->delayBetweenPagesMs)->milliseconds();
            }
        }
    }

    /**
     * Vuelve a procesar las páginas guardadas en raw_api_pages, sin tocar la red.
     */
    private function syncFromRaw(string $entity, SyncReport $report, Closure $onProgress): void
    {
        $rawPages = RawApiPage::query()->where('entity', $entity)->orderBy('page')->get();

        if ($rawPages->isEmpty()) {
            $report->warn("{$entity}: no hay JSON crudo guardado; ejecuta antes la sincronización normal.");

            return;
        }

        foreach ($rawPages as $rawPage) {
            try {
                $pageData = $this->parse($entity, $rawPage->payload);

                $this->persist($entity, $rawPage->page, $pageData, $report);

                $onProgress("{$entity}: página {$rawPage->page} reprocesada ({$this->count($pageData)})");
            } catch (InvalidExternalDataException $exception) {
                $report->pageFailed($entity, $rawPage->page, $exception->getMessage());
            }
        }
    }

    private function download(string $entity, int $page): PageData
    {
        return match ($entity) {
            'locations' => $this->client->locations($page),
            'episodes' => $this->client->episodes($page),
            'characters' => $this->client->characters($page),
        };
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function parse(string $entity, array $json): PageData
    {
        return match ($entity) {
            'locations' => $this->parser->locationsPage($json),
            'episodes' => $this->parser->episodesPage($json),
            'characters' => $this->parser->charactersPage($json),
        };
    }

    private function storeRaw(string $entity, int $page, PageData $pageData): void
    {
        RawApiPage::query()->updateOrCreate(
            ['entity' => $entity, 'page' => $page],
            ['payload' => $pageData->raw, 'fetched_at' => now()],
        );
    }

    /**
     * Guarda los registros de una página dentro de una transacción: o entra la
     * página entera o no entra nada.
     */
    private function persist(string $entity, int $page, PageData $pageData, SyncReport $report): void
    {
        DB::transaction(function () use ($entity, $pageData, $report) {
            match ($entity) {
                'locations' => $this->upsertLocations($pageData->items),
                'episodes' => $this->upsertEpisodes($pageData->items),
                'characters' => $this->upsertCharacters($pageData->items, $report),
            };
        });

        $report->pageSucceeded($entity, $page, count($pageData->items), $pageData->rejected);
    }

    /**
     * @param  list<LocationData>  $locations
     */
    private function upsertLocations(array $locations): void
    {
        $rows = array_map(fn (LocationData $location) => [
            'external_id' => $location->externalId,
            'name' => $location->name,
            'type' => $location->type,
            'dimension' => $location->dimension,
        ], $locations);

        // upsert = INSERT, o UPDATE si ya existe una fila con ese external_id.
        Location::query()->upsert($rows, uniqueBy: ['external_id'], update: ['name', 'type', 'dimension']);
    }

    /**
     * @param  list<EpisodeData>  $episodes
     */
    private function upsertEpisodes(array $episodes): void
    {
        $rows = array_map(fn (EpisodeData $episode) => [
            'external_id' => $episode->externalId,
            'name' => $episode->name,
            'air_date' => $episode->airDate?->toDateString(),
            'code' => $episode->code,
        ], $episodes);

        Episode::query()->upsert($rows, uniqueBy: ['external_id'], update: ['name', 'air_date', 'code']);
    }

    /**
     * Los personajes llegan con ids externos de localizaciones y episodios;
     * aquí se traducen a nuestras claves y se sincroniza la tabla pivote.
     *
     * @param  list<CharacterData>  $characters
     */
    private function upsertCharacters(array $characters, SyncReport $report): void
    {
        if ($characters === []) {
            return;
        }

        // Mapas external_id → id propio. Son tablas pequeñas (126 y 51 filas),
        // así que consultarlas en cada página es barato y mantiene el código simple.
        $locationIds = Location::query()->pluck('id', 'external_id');
        $episodeIds = Episode::query()->pluck('id', 'external_id');

        $rows = array_map(fn (CharacterData $character) => [
            'external_id' => $character->externalId,
            'name' => $character->name,
            'status' => $character->status,
            'species' => $character->species,
            'type' => $character->type,
            'gender' => $character->gender,
            'image' => $character->image,
            // Si la localización es "unknown" o todavía no se ha sincronizado, queda a NULL.
            'origin_location_id' => $locationIds[$character->originExternalId] ?? null,
            'current_location_id' => $locationIds[$character->currentLocationExternalId] ?? null,
        ], $characters);

        Character::query()->upsert(
            $rows,
            uniqueBy: ['external_id'],
            update: ['name', 'status', 'species', 'type', 'gender', 'image', 'origin_location_id', 'current_location_id'],
        );

        if ($episodeIds->isEmpty()) {
            $report->warn('characters: no hay episodios en la base de datos, no se han enlazado episodios.');

            return;
        }

        $this->syncEpisodes($characters, $episodeIds);
    }

    /**
     * Deja la tabla pivote exactamente como dice la API para cada personaje:
     * añade las relaciones nuevas y quita las que ya no estén.
     *
     * @param  list<CharacterData>  $characters
     * @param  Collection<int, int>  $episodeIds  external_id → id
     */
    private function syncEpisodes(array $characters, Collection $episodeIds): void
    {
        $models = Character::query()
            ->whereIn('external_id', array_map(fn (CharacterData $character) => $character->externalId, $characters))
            ->get()
            ->keyBy('external_id');

        foreach ($characters as $character) {
            $ids = collect($character->episodeExternalIds)
                ->map(fn (int $externalId) => $episodeIds[$externalId] ?? null)
                ->filter()
                ->values()
                ->all();

            $models[$character->externalId]->episodes()->sync($ids);
        }
    }

    private function count(PageData $pageData): string
    {
        $summary = count($pageData->items).' registros';

        if ($pageData->rejected !== []) {
            $summary .= ', '.count($pageData->rejected).' descartados';
        }

        return $summary;
    }
}
