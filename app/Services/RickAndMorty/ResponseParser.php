<?php

namespace App\Services\RickAndMorty;

use App\Services\RickAndMorty\DTO\CharacterData;
use App\Services\RickAndMorty\DTO\EpisodeData;
use App\Services\RickAndMorty\DTO\LocationData;
use App\Services\RickAndMorty\DTO\PageData;
use App\Services\RickAndMorty\Exceptions\InvalidExternalDataException;
use Illuminate\Support\Facades\Validator;

/**
 * Convierte el JSON de una página de la API en DTOs del dominio.
 *
 * Está separado del cliente HTTP para poder reutilizarlo con el JSON crudo
 * guardado en base de datos (reproceso sin volver a descargar).
 */
final class ResponseParser
{
    /**
     * @param  array<string, mixed>  $json
     */
    public function charactersPage(array $json): PageData
    {
        return $this->parsePage($json, CharacterData::fromArray(...));
    }

    /**
     * @param  array<string, mixed>  $json
     */
    public function episodesPage(array $json): PageData
    {
        return $this->parsePage($json, EpisodeData::fromArray(...));
    }

    /**
     * @param  array<string, mixed>  $json
     */
    public function locationsPage(array $json): PageData
    {
        return $this->parsePage($json, LocationData::fromArray(...));
    }

    /**
     * Valida la estructura de la página y convierte cada registro. Un registro
     * mal formado no tumba la página entera: se anota el motivo en "rejected"
     * y se sigue con el siguiente.
     *
     * @param  array<string, mixed>  $json
     * @param  callable(array<string, mixed>): (CharacterData|EpisodeData|LocationData)  $toDto
     */
    private function parsePage(array $json, callable $toDto): PageData
    {
        $this->assertPageShape($json);

        $items = [];
        $rejected = [];

        foreach ($json['results'] as $position => $record) {
            if (! is_array($record)) {
                $rejected[] = "El registro en la posición {$position} no es un objeto.";

                continue;
            }

            try {
                $items[] = $toDto($record);
            } catch (InvalidExternalDataException $exception) {
                $rejected[] = $exception->getMessage();
            }
        }

        return new PageData(
            totalPages: $json['info']['pages'],
            items: $items,
            rejected: $rejected,
            raw: $json,
        );
    }

    /**
     * Toda página de la API tiene la forma {"info": {"pages": N, ...}, "results": [...]}.
     *
     * @param  array<string, mixed>  $json
     */
    private function assertPageShape(array $json): void
    {
        $validator = Validator::make($json, [
            'info' => ['required', 'array'],
            'info.pages' => ['required', 'integer', 'min:1'],
            'results' => ['present', 'array'],
        ]);

        if ($validator->fails()) {
            throw InvalidExternalDataException::forPage($validator->errors()->first());
        }
    }
}
