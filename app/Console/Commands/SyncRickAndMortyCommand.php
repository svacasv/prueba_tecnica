<?php

namespace App\Console\Commands;

use App\Services\Sync\SyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rickandmorty:sync
    {--only=* : Entidades a sincronizar (locations, episodes, characters). Por defecto, todas}
    {--from-raw : Reprocesa el JSON crudo guardado en la base de datos sin descargar nada}')]
#[Description('Descarga personajes, episodios y localizaciones de la API de Rick and Morty y los guarda en la base de datos')]
class SyncRickAndMortyCommand extends Command
{
    /**
     * El comando solo se ocupa de leer opciones y mostrar resultados;
     * el trabajo lo hace SyncService.
     */
    public function handle(SyncService $sync): int
    {
        $entities = $this->option('only') ?: SyncService::ENTITIES;

        $unknown = array_diff($entities, SyncService::ENTITIES);

        if ($unknown !== []) {
            $this->error('Entidad desconocida: '.implode(', ', $unknown).'. Opciones: '.implode(', ', SyncService::ENTITIES));

            return self::INVALID;
        }

        $this->info($this->option('from-raw')
            ? 'Reprocesando el JSON crudo guardado…'
            : 'Descargando de la API de Rick and Morty…');

        $report = $sync->run(
            entities: array_values($entities),
            fromRaw: (bool) $this->option('from-raw'),
            onProgress: fn (string $line) => $this->line("  {$line}"),
        );

        $this->newLine();
        $this->table(
            ['Entidad', 'Páginas OK', 'Páginas fallidas', 'Registros', 'Descartados'],
            $report->rows(),
        );

        foreach ($report->warnings() as $warning) {
            $this->warn($warning);
        }

        foreach ($report->errors() as $error) {
            $this->error($error);
        }

        if ($report->hasFailures()) {
            $this->error('La sincronización terminó con errores. Vuelve a ejecutarla para completar las páginas que fallaron.');

            return self::FAILURE;
        }

        $this->info('Sincronización completada.');

        return self::SUCCESS;
    }
}
