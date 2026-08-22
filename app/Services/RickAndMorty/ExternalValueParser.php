<?php

namespace App\Services\RickAndMorty;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Conversión de los valores tal y como llegan de la API a los tipos del dominio.
 *
 * Son funciones puras: no tocan base de datos ni red, solo transforman un valor.
 */
final class ExternalValueParser
{
    /**
     * La API usa la cadena vacía ("") cuando no tiene dato. Aquí eso es NULL.
     */
    public static function nullIfEmpty(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Extrae el identificador numérico de una URL de recurso de la API.
     * Ejemplo: "https://rickandmortyapi.com/api/episode/12" → 12.
     *
     * Devuelve null si la URL está vacía (caso "unknown") o no tiene ese formato.
     */
    public static function idFromUrl(?string $url): ?int
    {
        if ($url === null || ! preg_match('~/api/[a-z]+/(\d+)/?$~', $url, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * Igual que idFromUrl pero para una lista. Las URLs que no se entienden se
     * descartan en silencio: es preferible perder una relación a abortar el registro.
     *
     * @param  array<int, mixed>  $urls
     * @return list<int>
     */
    public static function idsFromUrls(array $urls): array
    {
        $ids = [];

        foreach ($urls as $url) {
            $id = is_string($url) ? self::idFromUrl($url) : null;

            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Interpreta fechas con el formato que usa la API ("December 2, 2013").
     * Si no se puede interpretar devuelve null en lugar de fallar.
     */
    public static function dateOrNull(?string $value): ?CarbonImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('F j, Y', trim($value));
        } catch (Throwable) {
            return null;
        }

        return $date instanceof CarbonImmutable ? $date->startOfDay() : null;
    }
}
