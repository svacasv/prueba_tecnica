<?php

namespace App\Services\RickAndMorty\Exceptions;

use RuntimeException;

/**
 * La API respondió, pero el contenido no tiene la forma esperada: faltan campos,
 * los tipos no cuadran o la página no trae "info" y "results".
 */
class InvalidExternalDataException extends RuntimeException
{
    public static function forRecord(string $entity, mixed $externalId, string $reason): self
    {
        $id = $externalId === null ? 'sin id' : "id {$externalId}";

        return new self("Registro de {$entity} ({$id}) descartado: {$reason}");
    }

    public static function forPage(string $reason): self
    {
        return new self("La página recibida no tiene el formato esperado: {$reason}");
    }
}
