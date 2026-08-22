<?php

namespace App\Services\RickAndMorty\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Fallo al comunicarse con la API externa: red, tiempo de espera, código de
 * estado inesperado o cuerpo que no es JSON. La petición no se pudo aprovechar.
 */
class RickAndMortyApiException extends RuntimeException
{
    public static function connectionFailed(string $url, int $page, Throwable $previous): self
    {
        return new self(
            "No se pudo conectar con {$url} (página {$page}): {$previous->getMessage()}",
            previous: $previous,
        );
    }

    public static function unexpectedStatus(string $url, int $page, int $status): self
    {
        return new self("La API respondió con el código {$status} en {$url} (página {$page}).");
    }

    public static function invalidJson(string $url, int $page): self
    {
        return new self("La respuesta de {$url} (página {$page}) no es un JSON válido.");
    }
}
